<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Services\BatchWindowService;
use App\Support\PaginatedJson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BatchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 24), 1), 100);
        $search = trim((string) $request->query('search', ''));

        $query = Batch::query()->withCount('grantees')->latest();

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('academic_year', 'like', "%{$search}%")
                    ->orWhere('semester', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('window_status')) {
            if ($status !== 'all') {
                $query->where('window_status', $status);
            }
        }

        $paginator = $query->paginate($perPage);
        $rows = collect($paginator->items())->map(fn (Batch $batch) => $this->present($batch));

        return PaginatedJson::from($paginator, $rows->values());
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeMutation($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'academic_year' => ['required', 'string', 'max:40'],
            'semester' => ['required', 'string', 'max:80'],
            'submission_deadline' => ['required', 'date'],
        ]);

        $batch = Batch::create([
            ...$validated,
            'status' => 'draft',
            'window_status' => 'draft',
        ]);

        return response()->json(['data' => $this->present($batch->loadCount('grantees'))], 201);
    }

    public function show(Batch $batch): JsonResponse
    {
        $batch->loadCount('grantees')->load(['grantees.user']);

        return response()->json([
            'data' => [
                ...$this->present($batch),
                'grantees' => $batch->grantees->map(fn ($grantee) => [
                    'id' => $grantee->id,
                    'student_id' => $grantee->student_id,
                    'student_number' => $grantee->student_number,
                    'full_name' => $grantee->full_name,
                    'email' => $grantee->email,
                    'program' => $grantee->program,
                    'status' => $grantee->status,
                    'account_status' => $grantee->user?->account_status,
                ])->values(),
            ],
        ]);
    }

    public function update(Request $request, Batch $batch): JsonResponse
    {
        $this->authorizeMutation($request);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'academic_year' => ['sometimes', 'required', 'string', 'max:40'],
            'semester' => ['sometimes', 'required', 'string', 'max:80'],
            'submission_deadline' => ['sometimes', 'required', 'date'],
        ]);

        $batch->update($validated);

        return response()->json(['data' => $this->present($batch->fresh()->loadCount('grantees'))]);
    }

    public function activate(Request $request, Batch $batch, BatchWindowService $windows): JsonResponse
    {
        $this->authorizeMutation($request);

        DB::transaction(function () use ($batch): void {
            Batch::query()->where('id', '!=', $batch->id)->where('is_active', true)->update([
                'is_active' => false,
                'window_status' => 'closed',
                'closed_at' => now(),
                'status' => 'closed',
            ]);

            $batch->update([
                'is_active' => true,
                'window_status' => 'active',
                'activated_at' => now(),
                'closed_at' => null,
                'status' => 'active',
            ]);
        });

        $mail = $windows->notifyBatch(
            $batch->fresh(),
            'window_opened',
            'Submission window opened',
            "The submission window for {$batch->name} is now open until {$batch->submission_deadline?->toDayDateTimeString()}."
        );

        return response()->json(['data' => $this->present($batch->fresh()->loadCount('grantees')), 'mail' => $mail]);
    }

    public function deactivate(Request $request, Batch $batch, BatchWindowService $windows): JsonResponse
    {
        $this->authorizeMutation($request);

        $batch->update([
            'is_active' => false,
            'window_status' => 'closed',
            'closed_at' => now(),
            'status' => 'closed',
        ]);

        $mail = $windows->notifyBatch(
            $batch->fresh(),
            'window_closed',
            'Submission window closed',
            "The submission window for {$batch->name} is now closed."
        );

        return response()->json(['data' => $this->present($batch->fresh()->loadCount('grantees')), 'mail' => $mail]);
    }

    public function extendDeadline(Request $request, Batch $batch, BatchWindowService $windows): JsonResponse
    {
        $this->authorizeMutation($request);

        $validated = $request->validate([
            'submission_deadline' => ['required', 'date', 'after:now'],
        ]);

        $batch->update([
            'submission_deadline' => $validated['submission_deadline'],
            'window_status' => $batch->is_active ? 'active' : $batch->window_status,
            'closed_at' => $batch->is_active ? null : $batch->closed_at,
        ]);

        $mail = $windows->notifyBatch(
            $batch->fresh(),
            'deadline_extended',
            'Submission deadline extended',
            "The submission deadline for {$batch->name} was extended to {$batch->fresh()->submission_deadline?->toDayDateTimeString()}."
        );

        return response()->json(['data' => $this->present($batch->fresh()->loadCount('grantees')), 'mail' => $mail]);
    }

    private function authorizeMutation(Request $request): void
    {
        abort_unless(in_array($request->user()->role, ['admin', 'head'], true), 403);
    }

    private function present(Batch $batch): array
    {
        return [
            'id' => $batch->id,
            'name' => $batch->name,
            'academic_year' => $batch->academic_year,
            'semester' => $batch->semester,
            'submission_deadline' => $batch->submission_deadline,
            'is_active' => $batch->is_active,
            'window_status' => $batch->computedWindowStatus(),
            'activated_at' => $batch->activated_at,
            'closed_at' => $batch->closed_at,
            'grantees_count' => $batch->grantees_count ?? $batch->grantees()->count(),
        ];
    }
}
