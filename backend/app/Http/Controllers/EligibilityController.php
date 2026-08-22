<?php

namespace App\Http\Controllers;

use App\Events\NotificationCreated;
use App\Models\BatchNotification;
use App\Models\Grantee;
use App\Services\EligibilityPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EligibilityController extends Controller
{
    public function __construct(private readonly EligibilityPresenter $presenter) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 50), 1), 100);
        $batchId = $request->integer('batch_id') ?: null;

        $query = Grantee::query()
            ->with(['batch', 'pipelineResults' => fn ($q) => $q->latest('id')])
            ->where(function ($builder): void {
                $builder
                    ->whereHas('pipelineResults')
                    ->orWhereIn('status', ['eligible', 'not_eligible'])
                    ->orWhereIn('submission_status', ['docs_submitted', 'under_review', 'verified']);
            })
            ->latest('id');

        if ($batchId) {
            $query->where('batch_id', $batchId);
        }

        $paginator = $query->paginate($perPage);
        $rows = collect($paginator->items())
            ->map(fn (Grantee $grantee) => $this->presenter->listRow($grantee))
            ->values();

        $allForSummary = Grantee::query()
            ->with(['batch', 'pipelineResults' => fn ($q) => $q->latest('id')])
            ->where(function ($builder): void {
                $builder
                    ->whereHas('pipelineResults')
                    ->orWhereIn('status', ['eligible', 'not_eligible'])
                    ->orWhereIn('submission_status', ['docs_submitted', 'under_review', 'verified']);
            })
            ->when($batchId, fn ($q) => $q->where('batch_id', $batchId))
            ->get()
            ->map(fn (Grantee $grantee) => $this->presenter->listRow($grantee));

        return response()->json([
            'data' => $rows,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'summary' => [
                    'checked' => $allForSummary->count(),
                    'eligible' => $allForSummary->where('status', 'Eligible')->count(),
                    'needs_update' => $allForSummary->where('status', 'Needs update')->count(),
                    'not_eligible' => $allForSummary->where('status', 'Not eligible')->count(),
                ],
                'batches' => $allForSummary->pluck('batch')->unique()->filter()->values(),
            ],
        ]);
    }

    public function show(Grantee $grantee): JsonResponse
    {
        $grantee->load(['batch', 'user', 'pipelineResults' => fn ($q) => $q->latest('id')]);

        return response()->json(['data' => $this->presenter->detail($grantee)]);
    }

    public function notify(Request $request, Grantee $grantee): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $row = $this->presenter->listRow($grantee->load(['batch', 'pipelineResults' => fn ($q) => $q->latest('id')]));
        $body = trim((string) ($validated['message'] ?? ''));
        if ($body === '') {
            $body = sprintf(
                'Hello %s, your TES batch submission needs attention: %s Please update your requirements or visit the scholarship office for assistance.',
                $row['name'],
                $row['missing'],
            );
        }

        $userId = $grantee->user_id;
        if (! $userId) {
            return response()->json(['message' => 'Grantee has no linked portal account.'], 422);
        }

        $batchId = $grantee->batch_id;
        if (! $batchId) {
            return response()->json(['message' => 'Grantee is not assigned to a batch.'], 422);
        }

        $notification = BatchNotification::create([
            'batch_id' => $batchId,
            'user_id' => $userId,
            'type' => 'eligibility_notice',
            'title' => 'Submission eligibility notice',
            'body' => $body,
        ]);
        event(new NotificationCreated($notification));

        return response()->json([
            'data' => [
                'id' => $notification->id,
                'title' => $notification->title,
                'body' => $notification->body,
                'time' => $notification->created_at?->toDayDateTimeString(),
            ],
        ], 201);
    }
}
