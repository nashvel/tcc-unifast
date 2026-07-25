<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\BillingReport;
use App\Models\BillingReportItem;
use App\Models\Grantee;
use App\Models\User;
use App\Support\TesStipend;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BillingReportService
{
    public const DISK = 'public';

    public function generateCallForBilling(Batch $batch, User $actor, Request $request): BillingReport
    {
        $grantees = $this->batchGrantees($batch);
        $verified = $grantees->filter(fn (Grantee $g) => TesStipend::isVerified($g->status))->values();

        if ($verified->isEmpty()) {
            throw ValidationException::withMessages([
                'batch_id' => 'No verified grantees found in this batch for call-for-billing.',
            ]);
        }

        $rows = $verified->map(fn (Grantee $g) => $this->row($g, true))->all();

        return $this->persist(
            batch: $batch,
            actor: $actor,
            request: $request,
            type: BillingReport::TYPE_CALL_FOR_BILLING,
            rows: $rows,
            view: 'reports.call-for-billing',
            auditModule: 'Billing',
            auditAction: 'Generated call-for-billing report',
        );
    }

    public function generateDistribution(Batch $batch, User $actor, Request $request): BillingReport
    {
        $this->assertDistributionAllowed($batch);

        $rows = $this->batchGrantees($batch)->map(function (Grantee $grantee) {
            $verified = TesStipend::isVerified($grantee->status);

            return $this->row(
                $grantee,
                $verified,
                $verified ? null : TesStipend::exclusionReason($grantee->status),
            );
        })->all();

        return $this->persist(
            batch: $batch,
            actor: $actor,
            request: $request,
            type: BillingReport::TYPE_DISTRIBUTION,
            rows: $rows,
            view: 'reports.distribution',
            auditModule: 'Distribution',
            auditAction: 'Generated distribution report',
        );
    }

    public function download(BillingReport $report)
    {
        abort_unless($report->file_path && Storage::disk(self::DISK)->exists($report->file_path), 404);

        $filename = sprintf(
            '%s-batch-%s-%s.pdf',
            Str::of($report->type)->replace('_', '-'),
            $report->batch_id,
            optional($report->generated_at)->format('Ymd-His') ?? $report->id,
        );

        return Storage::disk(self::DISK)->download($report->file_path, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function persist(
        Batch $batch,
        User $actor,
        Request $request,
        string $type,
        array $rows,
        string $view,
        string $auditModule,
        string $auditAction,
    ): BillingReport {
        $stipend = TesStipend::AMOUNT_PER_SEMESTER;
        $included = collect($rows)->where('inclusion_status', BillingReportItem::INCLUDED);
        $totalAmount = round($included->count() * $stipend, 2);

        return DB::transaction(function () use (
            $batch,
            $actor,
            $request,
            $type,
            $rows,
            $view,
            $auditModule,
            $auditAction,
            $stipend,
            $included,
            $totalAmount,
        ) {
            $report = BillingReport::create([
                'batch_id' => $batch->id,
                'generated_by' => $actor->id,
                'type' => $type,
                'total_grantees' => $included->count(),
                'total_amount' => $totalAmount,
                'stipend_per_grantee' => $stipend,
                'is_supplemental' => false,
                'generated_at' => now(),
            ]);

            foreach ($rows as $row) {
                BillingReportItem::create([
                    'billing_report_id' => $report->id,
                    'grantee_id' => $row['grantee_id'],
                    'full_name' => $row['full_name'],
                    'student_id' => $row['student_id'],
                    'program' => $row['program'],
                    'stipend_amount' => $row['inclusion_status'] === BillingReportItem::INCLUDED ? $stipend : 0,
                    'inclusion_status' => $row['inclusion_status'],
                    'exclusion_reason' => $row['exclusion_reason'],
                ]);
            }

            $report->load(['batch', 'items', 'generator']);
            $includedItems = $report->items->where('inclusion_status', BillingReportItem::INCLUDED)->values();
            $excludedItems = $report->items->where('inclusion_status', BillingReportItem::EXCLUDED)->values();

            $pdf = Pdf::loadView($view, [
                'report' => $report,
                'batch' => $batch,
                'items' => $report->items,
                'includedItems' => $includedItems,
                'excludedItems' => $excludedItems,
                'stipend' => $stipend,
                'generatedAt' => $report->generated_at,
                'generatedBy' => $actor->name,
                'college' => config('app.college_name', 'Tagoloan Community College'),
                'programTitle' => 'UniFAST Tertiary Education Subsidy (TES)',
            ])->setPaper('a4', 'portrait');

            $path = sprintf(
                'billing-reports/%s/%s-%s.pdf',
                $type,
                $batch->id,
                now()->format('YmdHis').'-'.$report->id,
            );
            Storage::disk(self::DISK)->put($path, $pdf->output());
            $report->update(['file_path' => $path]);

            AuditLog::create([
                'actor' => $actor->name,
                'role' => ucfirst((string) $actor->role),
                'action' => $auditAction,
                'module' => $auditModule,
                'target' => "Batch #{$batch->id} / Report #{$report->id}",
                'context' => [
                    'batch_id' => $batch->id,
                    'report_id' => $report->id,
                    'type' => $type,
                    'verified_count' => $included->count(),
                    'excluded_count' => count($rows) - $included->count(),
                    'total_amount' => $totalAmount,
                    'file_path' => $path,
                ],
                'ip_address' => $request->ip(),
            ]);

            return $report->fresh(['batch', 'generator', 'items']);
        });
    }

    private function batchGrantees(Batch $batch)
    {
        return Grantee::query()
            ->where('batch_id', $batch->id)
            ->orderBy('full_name')
            ->get();
    }

    private function assertDistributionAllowed(Batch $batch): void
    {
        $status = $batch->window_status ?: $batch->computedWindowStatus();

        if (! in_array($status, ['closed', 'expired'], true) && ! $batch->closed_at) {
            throw ValidationException::withMessages([
                'batch_id' => 'Distribution reports can be generated only after the batch/distribution window is closed.',
            ]);
        }
    }

    private function row(Grantee $grantee, bool $included, ?string $exclusionReason = null): array
    {
        return [
            'grantee_id' => $grantee->id,
            'full_name' => $grantee->full_name,
            'student_id' => $grantee->student_id,
            'program' => $grantee->program,
            'inclusion_status' => $included ? BillingReportItem::INCLUDED : BillingReportItem::EXCLUDED,
            'exclusion_reason' => $included ? null : $exclusionReason,
        ];
    }
}
