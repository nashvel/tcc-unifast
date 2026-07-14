<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DocumentSubmission;
use App\Models\Grantee;
use App\Models\RequirementIdentityCheck;
use App\Services\BatchWindowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RequirementVaultController extends Controller
{
    private const SCHOOL_ID_SLOT = 'school_id';
    private const COURSE_HISTORY_SLOT = 'course_history';
    private const GRADE_SLIP_SLOT = 'grade_slip';

    public function show(Request $request, BatchWindowService $windows): JsonResponse
    {
        $context = $this->studentContext($request, $windows, false);

        return response()->json([
            'window' => $context['window'],
            'grantee' => $context['grantee'] ? $this->presentGrantee($context['grantee']) : null,
            'slots' => $context['grantee'] ? $this->presentSlots($context['grantee']) : [],
            'identity_check' => $context['grantee'] ? $this->latestIdentityCheck($context['grantee']) : null,
        ]);
    }

    public function storeId(Request $request, BatchWindowService $windows): JsonResponse
    {
        $context = $this->studentContext($request, $windows);
        $grantee = $context['grantee'];
        $batchId = (int) $context['window']['batch']['id'];

        $validated = $request->validate([
            'id_front' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'id_back' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'face_descriptor' => ['required', 'array', 'size:128'],
            'face_descriptor.*' => ['required', 'numeric'],
            'face_quality_score' => ['required', 'numeric', 'min:0', 'max:1'],
        ]);

        $front = $validated['id_front'];
        $back = $validated['id_back'];
        $frontPath = $front->store('submissions', 'public');
        $backPath = $back->store('submissions', 'public');
        $quality = (float) $validated['face_quality_score'];
        $manualReview = $quality < 0.7;

        $submission = DocumentSubmission::updateOrCreate(
            [
                'student_id' => $request->user()->student_id,
                'batch_id' => $batchId,
                'slot_key' => self::SCHOOL_ID_SLOT,
            ],
            [
                'grantee_id' => $grantee->id,
                'student_name' => $request->user()->name,
                'document_type' => 'School ID',
                'original_name' => $front->getClientOriginalName(),
                'stored_path' => $frontPath,
                'mime_type' => $front->getMimeType(),
                'file_size' => $front->getSize(),
                'secondary_original_name' => $back->getClientOriginalName(),
                'secondary_stored_path' => $backPath,
                'secondary_mime_type' => $back->getMimeType(),
                'secondary_file_size' => $back->getSize(),
                'status' => 'pending_review',
                'risk_level' => $manualReview ? 'medium' : 'low',
                'face_descriptor_payload' => array_map('floatval', $validated['face_descriptor']),
                'face_quality_score' => $quality,
                'identity_review_required' => $manualReview,
                'identity_review_reason' => $manualReview ? 'School ID face quality is below 0.70.' : null,
            ],
        );

        $this->audit($request, 'school_id_uploaded', $submission, [
            'quality' => $quality,
            'manual_review_required' => $manualReview,
        ]);

        return response()->json(['data' => $this->presentVaultDocument($submission)]);
    }

    public function storeDocument(Request $request, BatchWindowService $windows): JsonResponse
    {
        $context = $this->studentContext($request, $windows);
        $grantee = $context['grantee'];
        $batchId = (int) $context['window']['batch']['id'];

        $this->requireSlot($request->user()->student_id, $batchId, self::SCHOOL_ID_SLOT);

        $validated = $request->validate([
            'slot_key' => ['required', Rule::in([self::COURSE_HISTORY_SLOT, self::GRADE_SLIP_SLOT])],
            'file' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ]);

        $file = $validated['file'];
        $slotKey = $validated['slot_key'];
        $path = $file->store('submissions', 'public');
        $label = $slotKey === self::COURSE_HISTORY_SLOT ? 'Course History' : 'Grade Slip';

        $submission = DocumentSubmission::updateOrCreate(
            [
                'student_id' => $request->user()->student_id,
                'batch_id' => $batchId,
                'slot_key' => $slotKey,
            ],
            [
                'grantee_id' => $grantee->id,
                'student_name' => $request->user()->name,
                'document_type' => $label,
                'original_name' => $file->getClientOriginalName(),
                'stored_path' => $path,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'status' => 'pending_review',
                'risk_level' => 'low',
            ],
        );

        $this->audit($request, 'requirement_uploaded', $submission, ['slot' => $slotKey]);

        return response()->json(['data' => $this->presentVaultDocument($submission)]);
    }

    public function storeIdentityCheck(Request $request, BatchWindowService $windows): JsonResponse
    {
        $context = $this->studentContext($request, $windows);
        $grantee = $context['grantee'];
        $batchId = (int) $context['window']['batch']['id'];
        $schoolId = $this->requireSlot($request->user()->student_id, $batchId, self::SCHOOL_ID_SLOT);

        $validated = $request->validate([
            'challenge_sequence' => ['required', 'array', 'size:3'],
            'challenge_sequence.*' => ['required', Rule::in(['blink', 'turn_left', 'turn_right'])],
            'result' => ['required', Rule::in(['match', 'no_match'])],
            'distance' => ['required', 'numeric', 'min:0'],
            'confidence_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'consent_accepted' => ['accepted'],
        ]);

        $manualReview = $validated['result'] !== 'match' || (float) $validated['distance'] >= 0.5;
        $checkedAt = now();

        $check = RequirementIdentityCheck::create([
            'user_id' => $request->user()->id,
            'grantee_id' => $grantee->id,
            'batch_id' => $batchId,
            'document_submission_id' => $schoolId->id,
            'challenge_sequence' => $validated['challenge_sequence'],
            'result' => $validated['result'],
            'distance' => $validated['distance'],
            'confidence_score' => $validated['confidence_score'] ?? null,
            'manual_review_required' => $manualReview,
            'consent_accepted_at' => $checkedAt,
            'checked_at' => $checkedAt,
            'ip_address' => $request->ip(),
        ]);

        if ($manualReview) {
            $schoolId->update([
                'identity_review_required' => true,
                'identity_review_reason' => 'Live face did not match the School ID reference.',
                'risk_level' => 'high',
            ]);
        }

        $this->audit($request, 'identity_check_logged', $schoolId, [
            'result' => $check->result,
            'distance' => $check->distance,
            'manual_review_required' => $manualReview,
        ]);

        return response()->json(['data' => $this->presentIdentityCheck($check)]);
    }

    public function confirm(Request $request, BatchWindowService $windows): JsonResponse
    {
        $context = $this->studentContext($request, $windows);
        $grantee = $context['grantee'];
        $batchId = (int) $context['window']['batch']['id'];
        $studentId = $request->user()->student_id;

        foreach ([self::SCHOOL_ID_SLOT, self::COURSE_HISTORY_SLOT, self::GRADE_SLIP_SLOT] as $slot) {
            $this->requireSlot($studentId, $batchId, $slot);
        }

        $check = RequirementIdentityCheck::query()
            ->where('grantee_id', $grantee->id)
            ->where('batch_id', $batchId)
            ->latest('checked_at')
            ->first();

        if (! $check) {
            throw ValidationException::withMessages([
                'identity_check' => 'Complete the liveness and face match check before confirming.',
            ]);
        }

        $grantee->update([
            'submission_status' => 'docs_submitted',
            'submitted_at' => now(),
        ]);

        AuditLog::create([
            'actor' => $request->user()->name,
            'role' => 'Student',
            'action' => 'requirements_confirmed',
            'module' => 'Requirements Submission',
            'target' => "Grantee #{$grantee->id}",
            'context' => ['batch_id' => $batchId, 'identity_result' => $check->result],
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'grantee' => $this->presentGrantee($grantee->fresh()),
            'identity_check' => $this->presentIdentityCheck($check),
        ]);
    }

    private function studentContext(Request $request, BatchWindowService $windows, bool $requireOpen = true): array
    {
        $window = $windows->windowForStudent($request->user());
        if ($requireOpen && ! $window['open']) {
            throw ValidationException::withMessages(['submission_window' => $window['message']]);
        }

        $grantee = Grantee::query()
            ->where('user_id', $request->user()->id)
            ->orWhere('student_id', $request->user()->student_id)
            ->first();

        if ($requireOpen && ! $grantee) {
            throw ValidationException::withMessages(['grantee' => 'Your grantee profile is not assigned.']);
        }

        return ['window' => $window, 'grantee' => $grantee];
    }

    private function requireSlot(string $studentId, int $batchId, string $slotKey): DocumentSubmission
    {
        $submission = DocumentSubmission::query()
            ->where('student_id', $studentId)
            ->where('batch_id', $batchId)
            ->where('slot_key', $slotKey)
            ->first();

        if (! $submission) {
            throw ValidationException::withMessages([
                'slot_key' => 'Complete the School ID slot before continuing.',
            ]);
        }

        return $submission;
    }

    private function presentSlots(Grantee $grantee): array
    {
        return DocumentSubmission::query()
            ->where('grantee_id', $grantee->id)
            ->where('batch_id', $grantee->batch_id)
            ->get()
            ->mapWithKeys(fn (DocumentSubmission $item) => [$item->slot_key => $this->presentVaultDocument($item)])
            ->all();
    }

    private function latestIdentityCheck(Grantee $grantee): ?array
    {
        $check = RequirementIdentityCheck::query()
            ->where('grantee_id', $grantee->id)
            ->where('batch_id', $grantee->batch_id)
            ->latest('checked_at')
            ->first();

        return $check ? $this->presentIdentityCheck($check) : null;
    }

    private function presentGrantee(Grantee $grantee): array
    {
        return [
            'id' => $grantee->id,
            'student_id' => $grantee->student_id,
            'full_name' => $grantee->full_name,
            'submission_status' => $grantee->submission_status ?? 'not_submitted',
            'submitted_at' => $grantee->submitted_at,
        ];
    }

    private function presentVaultDocument(DocumentSubmission $item): array
    {
        return [
            'id' => $item->id,
            'slot_key' => $item->slot_key,
            'document_type' => $item->document_type,
            'original_name' => $item->original_name,
            'secondary_original_name' => $item->secondary_original_name,
            'status' => $item->status,
            'risk_level' => $item->risk_level,
            'face_quality_score' => $item->face_quality_score,
            'identity_review_required' => $item->identity_review_required,
            'identity_review_reason' => $item->identity_review_reason,
            'created_at' => $item->created_at,
            'file_url' => Storage::disk('public')->url($item->stored_path),
            'secondary_file_url' => $item->secondary_stored_path ? Storage::disk('public')->url($item->secondary_stored_path) : null,
            'face_descriptor' => $item->slot_key === self::SCHOOL_ID_SLOT ? $item->face_descriptor_payload : null,
        ];
    }

    private function presentIdentityCheck(RequirementIdentityCheck $check): array
    {
        return [
            'id' => $check->id,
            'challenge_sequence' => $check->challenge_sequence,
            'result' => $check->result,
            'distance' => $check->distance,
            'confidence_score' => $check->confidence_score,
            'manual_review_required' => $check->manual_review_required,
            'consent_accepted_at' => $check->consent_accepted_at,
            'checked_at' => $check->checked_at,
        ];
    }

    private function audit(Request $request, string $action, DocumentSubmission $submission, array $context): void
    {
        AuditLog::create([
            'actor' => $request->user()->name,
            'role' => 'Student',
            'action' => $action,
            'module' => 'Requirements Submission',
            'target' => "Submission #{$submission->id}",
            'context' => $context,
            'ip_address' => $request->ip(),
        ]);
    }
}
