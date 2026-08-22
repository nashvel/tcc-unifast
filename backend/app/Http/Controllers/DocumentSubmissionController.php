<?php

namespace App\Http\Controllers;

use App\Events\NotificationCreated;
use App\Models\AuditLog;
use App\Models\BatchNotification;
use App\Models\DocumentSubmission;
use App\Models\Grantee;
use App\Models\User;
use App\Support\PaginatedJson;
use App\Support\VaultFileStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
        $rows = collect($paginator->items())->map(fn (DocumentSubmission $item) => $this->present($item, $request));

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
                $expected = count(self::EXPECTED_SLOTS);
                $query->select(DB::raw(1))
                    ->from('document_submissions as complete_slots')
                    ->whereColumn('complete_slots.grantee_id', 'document_submissions.grantee_id')
                    ->whereColumn('complete_slots.batch_id', 'document_submissions.batch_id')
                    ->where('complete_slots.status', '!=', 'draft')
                    ->whereIn('complete_slots.slot_key', self::EXPECTED_SLOTS)
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

            return $this->presentPackage($group);
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

        return response()->json(['data' => $this->presentPackage($documents)]);
    }

    public function show(Request $request, DocumentSubmission $submission): JsonResponse
    {
        if ($request->user()->role === 'student') {
            abort_unless($submission->student_id === $request->user()->student_id, 403);
        }

        return response()->json(['data' => $this->present($submission, $request)]);
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

        return response()->json(['data' => $this->present($submission->fresh(), $request)]);
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

    public function audit(): JsonResponse
    {
        return response()->json(['data' => AuditLog::query()->latest()->limit(250)->get()]);
    }

    /**
     * @param  Collection<int, DocumentSubmission>  $documents
     * @return array<string, mixed>
     */
    private function presentPackage(Collection $documents): array
    {
        $ordered = $documents
            ->sortBy(function (DocumentSubmission $doc): int {
                $index = array_search((string) $doc->slot_key, self::EXPECTED_SLOTS, true);

                return $index === false ? 99 : $index;
            })
            ->values();

        /** @var DocumentSubmission $first */
        $first = $ordered->first();
        $statuses = $ordered->pluck('status')->filter()->values()->all();
        $riskRank = ['high' => 3, 'medium' => 2, 'low' => 1];
        $highestRisk = $ordered
            ->sortByDesc(fn (DocumentSubmission $doc) => $riskRank[$doc->risk_level] ?? 0)
            ->first();

        $submitted = $ordered->count();
        $expected = count(self::EXPECTED_SLOTS);
        $reviewed = $ordered
            ->filter(fn (DocumentSubmission $doc) => in_array($doc->status, ['approved', 'rejected', 'resubmission'], true))
            ->count();

        $submittedAtRaw = $ordered->max('created_at');
        $submittedAt = $submittedAtRaw
            ? Carbon::parse($submittedAtRaw)->toISOString()
            : null;

        return [
            'grantee_id' => (int) $first->grantee_id,
            'batch_id' => (int) $first->batch_id,
            'batch_name' => $first->batch?->name,
            'student_name' => $first->student_name,
            'student_id' => $first->student_id,
            'status' => $this->packageOverallStatus($statuses),
            'risk_level' => $highestRisk?->risk_level ?? 'low',
            'identity_review_required' => $ordered->contains(
                fn (DocumentSubmission $doc) => (bool) $doc->identity_review_required
            ),
            'submitted_at' => $submittedAt,
            'slots_expected' => $expected,
            'slots_submitted' => $submitted,
            'slots_reviewed' => $reviewed,
            'progress' => "{$submitted}/{$expected}",
            'documents' => $ordered->map(function (DocumentSubmission $doc): array {
                $slot = (string) ($doc->slot_key ?? '');

                return [
                    'id' => $doc->id,
                    'slot_key' => $doc->slot_key,
                    'document_type' => $doc->document_type,
                    'tab_label' => self::SLOT_TAB_LABELS[$slot] ?? $doc->document_type,
                    'status' => $doc->status,
                    'risk_level' => $doc->risk_level,
                    'identity_review_required' => (bool) $doc->identity_review_required,
                ];
            })->values()->all(),
        ];
    }

    /**
     * @param  list<string>  $statuses
     */
    private function packageOverallStatus(array $statuses): string
    {
        $unique = array_values(array_unique($statuses));
        if ($unique === []) {
            return 'pending_review';
        }
        if (count($unique) === 1) {
            return $unique[0];
        }
        if (in_array('rejected', $unique, true)) {
            return 'rejected';
        }
        if (in_array('resubmission', $unique, true)) {
            return 'resubmission';
        }
        if (in_array('pending_review', $unique, true) || in_array('processing', $unique, true)) {
            return 'pending_review';
        }

        return 'partially_reviewed';
    }

    private function present(DocumentSubmission $item, ?Request $request = null): array
    {
        $latestCheck = $item->identityChecks()->latest('checked_at')->first();
        $data = $item->toArray();
        unset(
            $data['face_descriptor_payload'],
            $data['stored_path'],
            $data['secondary_stored_path'],
        );

        // Drop internal absolute storage hints from metadata presented to clients.
        if (isset($data['metadata_payload']) && is_array($data['metadata_payload'])) {
            unset($data['metadata_payload']['frame_path']);
        }

        $userId = $request?->user()?->id;

        return array_merge($data, [
            'file_url' => VaultFileStorage::authSubmissionUrl($item, 'primary'),
            'secondary_file_url' => VaultFileStorage::authSubmissionUrl($item, 'secondary'),
            // Short-lived signed URLs for <img>/<iframe> without Bearer headers.
            'file_preview_url' => VaultFileStorage::signedSubmissionUrl($item, 'primary', $userId),
            'secondary_file_preview_url' => VaultFileStorage::signedSubmissionUrl($item, 'secondary', $userId),
            'identity_check' => $latestCheck ? [
                'result' => $latestCheck->result,
                'distance' => $latestCheck->distance,
                'confidence_score' => $latestCheck->confidence_score,
                'manual_review_required' => $latestCheck->manual_review_required,
                'challenge_sequence' => $latestCheck->challenge_sequence,
                'checked_at' => $latestCheck->checked_at,
            ] : null,
        ]);
    }
}
