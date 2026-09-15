<?php

namespace App\Services\Continuity;

use App\Models\ContinuityGrant;
use App\Models\ContinuityRecordState;
use App\Models\ContinuityResource;
use App\Models\ContinuityReview;
use App\Models\ContinuityRevision;
use App\Models\ContinuitySyncRun;
use App\Models\GoogleWorkspaceConnection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContinuitySyncService
{
    public function __construct(private ModuleRegistry $modules, private WorkbookService $sheets, private ThreeWayMerge $merge, private WorkspaceGrantService $grants) {}

    public function run(ContinuitySyncRun $run): void
    {
        Cache::lock('continuity:sync', 1800)->block(5, function () use ($run): void {
            $connection = GoogleWorkspaceConnection::first();
            abort_unless(config('continuity.enabled') && $connection?->enabled && $connection->status === 'connected', 409, 'Continuity synchronization is disabled.');
            $run->update(['status' => 'running', 'started_at' => now(), 'error_code' => null]);
            $summary = [];
            foreach (ContinuityGrant::where('status', '!=', 'revoked')->get() as $grant) {
                $this->grants->reconcile($grant);
            }
            foreach (ContinuityResource::where('status', 'ready')->get() as $resource) {
                try {
                    $this->sheets->verifyPrivate($resource);
                    $summary[$resource->module] = $this->syncModule($resource);
                } catch (\Throwable) {
                    // Queue/execution failures must not retain Google responses or student rows.
                    $summary[$resource->module] = ['status' => 'failed', 'error_code' => 'module_sync_failed'];
                }
            }
            $failed = collect($summary)->contains(fn ($item) => $item['status'] === 'failed');
            $run->update(['status' => $failed ? 'partial_failure' : ($summary ? 'completed' : 'failed'), 'summary' => $summary, 'finished_at' => now(), 'error_code' => $summary ? null : 'no_ready_resources']);
        });
    }

    private function syncModule(ContinuityResource $resource): array
    {
        $fields = $this->modules->fields($resource->module);
        $headers = ['Record reference', 'Revision', ...$fields];
        $changes = $this->sheets->read($resource, 'Changes!A:Z');
        abort_if(count($changes) > 10000, 409, 'Archive reviewed change history before importing more rows.');
        abort_unless(($changes[0] ?? []) === $headers, 409, 'Workbook schema mismatch.');
        $imported = 0;
        foreach (array_slice($changes, 1) as $row) {
            if (! array_filter($row, fn ($value) => $value !== '')) {
                continue;
            }
            $this->import($resource->module, $row);
            $imported++;
        }
        $rows = [$headers];
        $this->modules->query($resource->module)->orderBy('id')->chunkById(250, function ($records) use ($resource, &$rows): void {
            foreach ($records as $record) {
                $snapshot = $this->modules->snapshot($resource->module, $record);
                $state = DB::transaction(function () use ($resource, $record, $snapshot) {
                    $state = ContinuityRecordState::firstOrCreate(['module' => $resource->module, 'record_id' => $record->id]);
                    $state = ContinuityRecordState::lockForUpdate()->findOrFail($state->id);
                    if ($state->base !== $snapshot) {
                        $state->revision++;
                        $state->base = $snapshot;
                        $state->save();
                        ContinuityRevision::create(['record_state_id' => $state->id, 'revision' => $state->revision, 'snapshot' => $snapshot]);
                    }

                    return $state;
                });
                $rows[] = [$state->id, (string) $state->revision, ...array_values($snapshot)];
            }
        });
        // Only system-owned Records is rewritten; staff-owned Changes is never cleared.
        $this->sheets->prepareRecords($resource, count($rows));
        foreach (array_chunk($rows, 250) as $index => $chunk) {
            $this->sheets->write($resource, 'Records!A'.($index * 250 + 1), $chunk);
        }
        ContinuityRecordState::where('module', $resource->module)->update(['synced_at' => now()]);

        return ['status' => 'completed', 'exported' => count($rows) - 1, 'changes_seen' => $imported];
    }

    public function import(string $module, array $row): void
    {
        $fields = $this->modules->fields($module);
        abort_unless(count($row) <= count($fields) + 2 && Str::isUuid((string) ($row[0] ?? '')) && ctype_digit((string) ($row[1] ?? '')), 422, 'Invalid record reference or revision.');
        $row = array_pad($row, count($fields) + 2, '');
        foreach ($row as $value) {
            abort_unless(is_scalar($value) && mb_strlen((string) $value) <= 10000, 422, 'Invalid field value.');
            abort_if(preg_match('/^[=+@]/', (string) $value) === 1, 422, 'Formulas are not accepted as business values.');
        }
        $mirror = array_combine($fields, array_map('strval', array_slice($row, 2)));
        DB::transaction(function () use ($module, $row, $mirror): void {
            $state = ContinuityRecordState::where('module', $module)->lockForUpdate()->findOrFail($row[0]);
            $revision = ContinuityRevision::where('record_state_id', $state->id)->where('revision', (int) $row[1])->firstOrFail();
            $fingerprint = hash('sha256', json_encode([$state->id, $revision->revision, $mirror], JSON_THROW_ON_ERROR));
            if (ContinuityReview::where('fingerprint', $fingerprint)->exists()) {
                return;
            }
            $record = $this->modules->query($module)->lockForUpdate()->findOrFail($state->record_id);
            $system = $this->modules->snapshot($module, $record);
            $result = $this->merge->compare($revision->snapshot, $system, $mirror, $this->modules->fields($module), array_diff($this->modules->fields($module), $this->modules->editable($module)));
            $updates = array_diff_assoc($result['merged'], $system);
            if ($updates) {
                validator($updates, array_fill_keys(array_keys($updates), ['nullable', 'string', 'max:2000']))->validate();
                // Only explicitly allowed low-impact fields can reach a model update.
                $record->fill($updates)->save();
            }
            ContinuityReview::create([
                'record_state_id' => $state->id, 'module' => $module, 'fingerprint' => $fingerprint,
                'kind' => $result['conflicts'] ? 'conflict' : ($result['approvals'] ? 'approval' : 'change'),
                'status' => ($result['conflicts'] || $result['approvals']) ? 'pending' : 'merged',
                'payload' => ['base' => $revision->snapshot, 'system' => $system, 'mirror' => $mirror, 'conflicts' => $result['conflicts'], 'approvals' => $result['approvals']],
            ]);
        });
    }
}
