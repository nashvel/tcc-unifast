<?php

namespace App\Http\Controllers;

use App\Events\NotificationCreated;
use App\Models\AuditLog;
use App\Models\BatchNotification;
use App\Models\DocumentSubmission;
use App\Models\Grantee;
use App\Models\User;
use App\Services\DocumentSubmissionPresenter;
use App\Support\PaginatedJson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DocumentSubmissionController extends Controller
{
    /** @var list<string> */
    private const EXPECTED_SLOTS = [
        'course_history',
        'grade_slip',
        'specimen_signatures',
    ];

    /** @var array<string, string> */
    private const SLOT_TAB_LABELS = [
        'course_history' => 'Course History',
        'grade_slip' => 'Grade Slip',
        'specimen_signatures' => 'ID (Back-to-Back) & Specimen',
    ];

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);
        $search = trim((string) $request->query('search', ''));
        $sort = (string) $request->query('sort', 'created_at');
        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['created_at', 'student_name', 'student_id', 'document_type', 'status', 'risk_level'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        $query = DocumentSubmission::query();
        if ($request->user()->role === 'student') {
            $query->where('student_id', $request->user()->student_id);
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('student_name', 'like', "%{$search}%")
                    ->orWhere('student_id', 'like', "%{$search}%")
                    ->orWhere('document_type', 'like', "%{$search}%")
                    ->orWhere('slot_key', 'like', "%{$search}%");
            });
        }

        $status = $request->query('status');
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        } elseif ($request->user()->role !== 'student' && (! $status || $status === '')) {
            // Staff validation queue: hide student drafts until final submit.
            $query->where('status', '!=', 'draft');
        }

        $paginator = $query->orderBy($sort, $direction)->paginate($perPage);
        $viewerId = $request->user()->id;
        $rows = collect($paginator->items())->map(
            fn (DocumentSubmission $item) => $this->presenter->submission($item, $viewerId)
        );

        return PaginatedJson::from($paginator, $rows->values());
    }

    /**
     * Staff validation queue: one row per grantee + batch package.
     */
    public function packages(Request $request): JsonResponse
    {
        abort_if($request->user()->role === 'student', 403);

        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);
        $search = trim((string) $request->query('search', ''));

        $base = DocumentSubmission::query()
            ->where('status', '!=', 'draft')
            ->whereNotNull('grantee_id')
            ->whereNotNull('batch_id')
            // Incomplete packages must not appear in Document Validation (initial submit is all-4).
            ->whereExists(function ($query): void {
                $expected = count(DocumentSubmissionPresenter::EXPECTED_SLOTS);
                $query->select(DB::raw(1))
                    ->from('document_submissions as complete_slots')
                    ->whereColumn('complete_slots.grantee_id', 'document_submissions.grantee_id')
                    ->whereColumn('complete_slots.batch_id', 'document_submissions.batch_id')
                    ->where('complete_slots.status', '!=', 'draft')
                    ->whereIn('complete_slots.slot_key', DocumentSubmissionPresenter::EXPECTED_SLOTS)
                    ->groupBy('complete_slots.grantee_id', 'complete_slots.batch_id')
                    ->havingRaw('COUNT(DISTINCT complete_slots.slot_key) = '.$expected);
            });

        if ($search !== '') {
            $base->where(function ($builder) use ($search): void {
                $builder
                    ->where('student_name', 'like', "%{$search}%")
                    ->orWhere('student_id', 'like', "%{$search}%");
            });
        }

        $status = $request->query('status');
        if ($status && $status !== 'all') {
            $base->where('status', $status);
        }

        $paginator = (clone $base)
            ->select([
                'grantee_id',
                'batch_id',
                DB::raw('MAX(created_at) as submitted_at'),
            ])
            ->groupBy('grantee_id', 'batch_id')
            ->orderByDesc('submitted_at')
            ->paginate($perPage);

        /** @var Collection<int, object{grantee_id: int|string, batch_id: int|string}> $keys */
        $keys = collect($paginator->items());
        if ($keys->isEmpty()) {
            return PaginatedJson::from($paginator, collect());
        }

        $documents = DocumentSubmission::query()
            ->with('batch:id,name')
            ->where('status', '!=', 'draft')
            ->where(function ($builder) use ($keys): void {
                foreach ($keys as $key) {
                    $builder->orWhere(function ($inner) use ($key): void {
                        $inner
                            ->where('grantee_id', $key->grantee_id)
                            ->where('batch_id', $key->batch_id);
                    });
                }
            })
            ->get()
            ->groupBy(fn (DocumentSubmission $doc) => $doc->grantee_id.'|'.$doc->batch_id);

        $rows = $keys->map(function (object $key) use ($documents): array {
            $group = $documents->get($key->grantee_id.'|'.$key->batch_id, collect());

            return $this->presenter->package($group);
        })->values();

        return PaginatedJson::from($paginator, $rows);
    }

    public function packageShow(Request $request, int $granteeId, int $batchId): JsonResponse
    {
        abort_if($request->user()->role === 'student', 403);

        $documents = DocumentSubmission::query()
            ->with('batch:id,name')
            ->where('grantee_id', $granteeId)
            ->where('batch_id', $batchId)
            ->where('status', '!=', 'draft')
            ->get();

        // Detail must load any staff-visible package (deep links / Retry), even if
        // fewer than 4 slots are filled. List still hides incomplete packages.
        abort_if($documents->isEmpty(), 404);

        return response()->json(['data' => $this->presenter->package($documents)]);
    }

    public function show(Request $request, DocumentSubmission $submission): JsonResponse
    {
        if ($request->user()->role === 'student') {
            abort_unless($submission->student_id === $request->user()->student_id, 403);
        }

        return response()->json(['data' => $this->presenter->submission($submission, $request->user()->id)]);
    }

    public function review(Request $request, DocumentSubmission $submission): JsonResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:approved,rejected,resubmission'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $submission->update([
            'status' => $validated['decision'],
            'review_notes' => $validated['notes'] ?? null,
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->name,
        ]);

        if ($validated['decision'] === 'resubmission' && $submission->grantee_id) {
            Grantee::query()->whereKey($submission->grantee_id)->update([
                'submission_status' => 'resubmission_requested',
            ]);
            $this->notifyStudentOfResubmission($submission, $validated['notes'] ?? null);
        }

        AuditLog::create([
            'actor' => $request->user()->name, 'role' => ucfirst($request->user()->role),
            'action' => 'submission_'.$validated['decision'], 'module' => 'Document Validation',
            'target' => "Submission #{$submission->id}", 'context' => ['notes' => $validated['notes'] ?? null],
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['data' => $this->presenter->submission($submission->fresh(), $request->user()->id)]);
    }

    private function notifyStudentOfResubmission(DocumentSubmission $submission, ?string $notes): void
    {
        $grantee = Grantee::query()->find($submission->grantee_id);
        if (! $grantee) {
            return;
        }

        $userId = $grantee->user_id
            ?: User::query()->where('student_id', $grantee->student_id)->value('id');
        if (! $userId || ! $grantee->batch_id) {
            return;
        }

        $docLabel = $submission->document_type ?: 'document';
        $body = "Staff requested a resubmission for your {$docLabel}.";
        if (is_string($notes) && trim($notes) !== '') {
            $body .= ' Notes: '.trim($notes);
        }

        $notification = BatchNotification::create([
            'batch_id' => $grantee->batch_id,
            'user_id' => $userId,
            'type' => 'resubmission_requested',
            'title' => 'Document returned — resubmission requested',
            'body' => $body,
        ]);
        event(new NotificationCreated($notification));
    }

    public function __construct(
        private readonly DocumentSubmissionPresenter $presenter,
    ) {}

}
