<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DocumentSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentSubmissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DocumentSubmission::query()->latest();
        if ($request->user()->role === 'student') $query->where('student_id', $request->user()->student_id);
        return response()->json(['data' => $query->get()->map(fn (DocumentSubmission $item) => $this->present($item))]);
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
