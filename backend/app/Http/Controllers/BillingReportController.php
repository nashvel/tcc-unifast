<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\BillingReport;
use App\Models\BillingReportItem;
use App\Services\BillingReportService;
use App\Support\PaginatedJson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BillingReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);

        $query = BillingReport::query()
            ->with(['batch', 'generator'])
            ->where('type', BillingReport::TYPE_CALL_FOR_BILLING)
            ->latest('generated_at')
            ->latest('id');

        if ($batchId = $request->integer('batch_id')) {
            $query->where('batch_id', $batchId);
        }

        $paginator = $query->paginate($perPage);
        $rows = collect($paginator->items())->map(fn (BillingReport $report) => $this->present($report));

        return PaginatedJson::from($paginator, $rows->values());
    }

    public function store(Request $request, BillingReportService $service): JsonResponse
    {
        $validated = $request->validate([
            'batch_id' => ['required', 'integer', 'exists:batches,id'],
        ]);

        $batch = Batch::query()->findOrFail($validated['batch_id']);
        $report = $service->generateCallForBilling($batch, $request->user(), $request);

        return response()->json(['data' => $this->present($report, true)], 201);
    }

    public function show(BillingReport $report): JsonResponse
    {
        abort_unless($report->type === BillingReport::TYPE_CALL_FOR_BILLING, 404);

        $report->load(['batch', 'generator', 'items']);

        return response()->json(['data' => $this->present($report, true)]);
    }

    public function download(BillingReport $report, BillingReportService $service): BinaryFileResponse|StreamedResponse
    {
        abort_unless($report->type === BillingReport::TYPE_CALL_FOR_BILLING, 404);

        return $service->download($report);
    }

    private function present(BillingReport $report, bool $withItems = false): array
    {
        $payload = [
            'id' => $report->id,
            'type' => $report->type,
            'batch_id' => $report->batch_id,
            'batch' => $report->batch ? [
                'id' => $report->batch->id,
                'name' => $report->batch->name,
                'academic_year' => $report->batch->academic_year,
                'semester' => $report->batch->semester,
            ] : null,
            'generated_by' => $report->generator?->name,
            'total_grantees' => $report->total_grantees,
            'total_amount' => (float) $report->total_amount,
            'stipend_per_grantee' => (float) $report->stipend_per_grantee,
            'file_path' => $report->file_path,
            'generated_at' => optional($report->generated_at)->toIso8601String(),
            'created_at' => optional($report->created_at)->toIso8601String(),
        ];

        if ($withItems) {
            $payload['items'] = $report->items->map(fn (BillingReportItem $item) => [
                'id' => $item->id,
                'grantee_id' => $item->grantee_id,
                'full_name' => $item->full_name,
                'student_id' => $item->student_id,
                'program' => $item->program,
                'stipend_amount' => (float) $item->stipend_amount,
                'inclusion_status' => $item->inclusion_status,
                'exclusion_reason' => $item->exclusion_reason,
            ])->values();
        }

        return $payload;
    }
}
