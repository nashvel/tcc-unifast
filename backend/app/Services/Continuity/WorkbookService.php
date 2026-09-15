<?php

namespace App\Services\Continuity;

use App\Models\ContinuityGrant;
use App\Models\ContinuityResource;
use App\Models\GoogleWorkspaceConnection;
use Illuminate\Support\Facades\Cache;

class WorkbookService
{
    public function __construct(private GoogleWorkspaceClient $google, private ModuleRegistry $modules) {}

    public function provision(): void
    {
        Cache::lock('continuity:resources', 1800)->block(5, function (): void {
            $connection = GoogleWorkspaceConnection::firstOrFail();
            abort_unless($connection->drive_id, 409, 'Select a Shared Drive first.');
            foreach (array_keys($this->modules->modules()) as $module) {
                $resource = ContinuityResource::firstOrCreate(['module' => $module]);
                if (! $resource->workbook_id) {
                    // Stable appProperties allow recovery after a timeout before the ID is saved.
                    $marker = 'unifast-continuity-'.$module;
                    $files = $this->google->api('GET', 'drive', 'files', ['query' => [
                        'q' => "trashed = false and appProperties has { key='continuity' and value='".$marker."' }",
                        'corpora' => 'drive', 'driveId' => $connection->drive_id,
                        'includeItemsFromAllDrives' => 'true', 'supportsAllDrives' => 'true', 'fields' => 'files(id)',
                    ]]);
                    abort_if(count($files['files'] ?? []) > 1, 409, 'Duplicate continuity workbooks need administrator review.');
                    $file = $files['files'][0] ?? $this->google->api('POST', 'drive', 'files', [
                        'query' => ['supportsAllDrives' => 'true', 'fields' => 'id'],
                        'json' => ['name' => 'UniFAST '.ucfirst($module), 'mimeType' => 'application/vnd.google-apps.spreadsheet', 'parents' => [$connection->drive_id], 'appProperties' => ['continuity' => $marker]],
                    ]);
                    abort_unless(is_string($file['id'] ?? null), 503, 'Google did not return a workbook ID.');
                    $resource->update(['workbook_id' => $file['id']]);
                }
                $this->verifyPrivate($resource);
                $metadata = $this->google->api('GET', 'sheets', rawurlencode($resource->workbook_id), ['query' => ['fields' => 'sheets(properties,protectedRanges)']]);
                $sheets = collect($metadata['sheets'] ?? [])->keyBy('properties.title');
                $requests = [];
                foreach (['Records' => 110, 'Changes' => 111, 'Instructions' => 112] as $name => $id) {
                    if (! $sheets->has($name)) {
                        $requests[] = ['addSheet' => ['properties' => ['sheetId' => $id, 'title' => $name]]];
                    } else {
                        abort_unless(($sheets[$name]['properties']['sheetId'] ?? null) === $id, 409, 'Workbook layout changed. Review it before repairing.');
                    }
                }
                if ($requests) {
                    $this->google->api('POST', 'sheets', $resource->workbook_id.':batchUpdate', ['json' => ['requests' => $requests]]);
                }
                foreach (['Records' => 110, 'Instructions' => 112] as $name => $id) {
                    if (empty($sheets[$name]['protectedRanges'])) {
                        $this->google->api('POST', 'sheets', $resource->workbook_id.':batchUpdate', ['json' => ['requests' => [[
                            'addProtectedRange' => ['protectedRange' => ['range' => ['sheetId' => $id], 'description' => 'Managed by UniFAST', 'warningOnly' => false, 'editors' => ['users' => [$connection->email]]]],
                        ]]]]);
                    }
                }
                if ($resource->status !== 'ready') {
                    $this->write($resource, 'Changes!A1', [['Record reference', 'Revision', ...$this->modules->fields($module)]]);
                    $this->write($resource, 'Instructions!A1', [
                        ['Copy a row from Records to Changes, then edit its business fields.'],
                        ['Keep the record reference and revision unchanged. Do not delete earlier changes.'],
                        ['UniFAST preserves submitted changes in its encrypted review history.'],
                        ['Decisions, amounts and identity changes require review in the live application.'],
                        ['Do not paste passwords, tokens, biometric data or technical logs.'],
                    ]);
                    $resource->update(['status' => 'ready']);
                }
            }
        });
    }

    public function verifyPrivate(ContinuityResource $resource): void
    {
        $connection = GoogleWorkspaceConnection::firstOrFail();
        $file = $this->google->api('GET', 'drive', 'files/'.rawurlencode($resource->workbook_id), ['query' => ['supportsAllDrives' => 'true', 'fields' => 'id,driveId,trashed,mimeType']]);
        abort_unless(($file['driveId'] ?? null) === $connection->drive_id && ! ($file['trashed'] ?? false) && ($file['mimeType'] ?? null) === 'application/vnd.google-apps.spreadsheet', 409, 'Workbook must remain in the selected Shared Drive.');
        $grants = ContinuityGrant::with('user.roles')->where('resource_id', $resource->id)
            ->whereIn('status', ['active', 'pending'])->get()->filter(fn ($grant) => $grant->isEligible())
            ->keyBy(fn ($grant) => strtolower($grant->email));
        $page = null;
        $seenPages = [];
        do {
            $result = $this->google->api('GET', 'drive', 'files/'.rawurlencode($resource->workbook_id).'/permissions', ['query' => array_filter(['supportsAllDrives' => 'true', 'fields' => 'nextPageToken,permissions(id,type,emailAddress,role)', 'pageToken' => $page])]);
            abort_unless(is_array($result['permissions'] ?? null), 503, 'Google did not return workbook access details.');
            foreach ($result['permissions'] ?? [] as $permission) {
                $email = strtolower($permission['emailAddress'] ?? '');
                abort_unless(($permission['type'] ?? '') === 'user' && $email !== '', 409, 'Remove group, public or domain-wide sharing before synchronization.');
                if ($email === strtolower($connection->email ?? '')) {
                    continue;
                }
                $grant = $grants->get($email);
                $allowedRoles = $grant?->access === 'write' ? ['reader', 'writer'] : ['reader'];
                abort_unless($grant && in_array($permission['role'] ?? '', $allowedRoles, true), 409, 'Workbook access exceeds administrator-approved permissions. Review file and Shared Drive membership.');
            }
            $page = $result['nextPageToken'] ?? null;
            if ($page) {
                abort_if(isset($seenPages[$page]) || count($seenPages) >= 100, 503, 'Google permission pagination could not be verified.');
                $seenPages[$page] = true;
            }
        } while ($page);
    }

    public function read(ContinuityResource $resource, string $range): array
    {
        return $this->google->api('GET', 'sheets', $resource->workbook_id.'/values/'.rawurlencode($range), ['query' => ['valueRenderOption' => 'FORMULA']])['values'] ?? [];
    }

    public function write(ContinuityResource $resource, string $range, array $values): void
    {
        $this->google->api('PUT', 'sheets', $resource->workbook_id.'/values/'.rawurlencode($range), ['query' => ['valueInputOption' => 'RAW'], 'json' => ['values' => $values]]);
    }

    public function prepareRecords(ContinuityResource $resource, int $count): void
    {
        $this->google->api('POST', 'sheets', $resource->workbook_id.':batchUpdate', ['json' => ['requests' => [[
            'updateSheetProperties' => ['properties' => ['sheetId' => 110, 'gridProperties' => ['rowCount' => max(1000, $count + 1)]], 'fields' => 'gridProperties.rowCount'],
        ]]]]);
        $this->google->api('POST', 'sheets', $resource->workbook_id.'/values/'.rawurlencode('Records!A:Z').':clear', ['json' => (object) []]);
    }
}
