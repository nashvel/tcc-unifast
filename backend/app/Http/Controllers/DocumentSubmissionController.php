<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DocumentSubmission;
use App\Support\PaginatedJson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentSubmissionController extends Controller
{
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

        if ($status = $request->query('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        $paginator = $query->orderBy($sort, $direction)->paginate($perPage);
        $rows = collect($paginator->items())->map(fn (DocumentSubmission $item) => $this->present($item));

        return PaginatedJson::from($paginator, $rows->values());
    }

    public function show(Request $request, DocumentSubmission $submission): JsonResponse
    {
        if ($request->user()->role === 'student') {
            abort_unless($submission->student_id === $request->user()->student_id, 403);
        }
        return response()->json(['data' => $this->present($submission)]);
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
        AuditLog::create([
            'actor' => $request->user()->name, 'role' => ucfirst($request->user()->role),
            'action' => 'submission_'.$validated['decision'], 'module' => 'Document Validation',
            'target' => "Submission #{$submission->id}", 'context' => ['notes' => $validated['notes'] ?? null],
            'ip_address' => $request->ip(),
        ]);
        return response()->json(['data' => $this->present($submission->fresh())]);
    }

    public function audit(): JsonResponse
    {
        return response()->json(['data' => AuditLog::query()->latest()->limit(250)->get()]);
    }

    private function present(DocumentSubmission $item): array
    {
        $latestCheck = $item->identityChecks()->latest('checked_at')->first();
        $data = $item->toArray();
        unset($data['face_descriptor_payload']);

        return array_merge($data, [
            'file_url' => Storage::disk('public')->url($item->stored_path),
            'secondary_file_url' => $item->secondary_stored_path ? Storage::disk('public')->url($item->secondary_stored_path) : null,
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
