<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessRequirementSubmissionPipeline;
use App\Models\AuditLog;
use App\Models\DocumentSubmission;
use App\Models\Grantee;
use App\Models\GranteeIdentityProfile;
use App\Models\RequirementIdentityCheck;
use App\Services\BatchWindowService;
use App\Services\IdCardOcrService;
use App\Services\MasterlistTruthService;
use App\Services\TccRegistrarQrService;
use App\Support\IdentityPhotoStorage;
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

    private const SPECIMEN_SIGNATURES_SLOT = 'specimen_signatures';

    public function show(Request $request, BatchWindowService $windows): JsonResponse
    {
        $context = $this->studentContext($request, $windows, false);
        $identity = $context['grantee']
            ? GranteeIdentityProfile::query()->where('grantee_id', $context['grantee']->id)->first()
            : null;

        return response()->json([
            'window' => $context['window'],
            'grantee' => $context['grantee'] ? $this->presentGrantee($context['grantee']) : null,
            'slots' => $context['grantee'] ? $this->presentSlots($context['grantee']) : [],
            'identity_check' => $context['grantee'] ? $this->latestIdentityCheck($context['grantee']) : null,
            'onboarding_refs' => $identity ? [
                'id_reference_face_url' => IdentityPhotoStorage::url($identity->id_reference_face_path),
                'onboarding_selfie_url' => IdentityPhotoStorage::url($identity->onboarding_selfie_path),
                'completed' => $identity->isComplete(),
            ] : null,
        ]);
    }

    public function storeId(
        Request $request,
        BatchWindowService $windows,
        IdCardOcrService $ocr,
        TccRegistrarQrService $qr,
        MasterlistTruthService $truth,
    ): JsonResponse {
        $context = $this->studentContext($request, $windows);
        $grantee = $context['grantee'];
        $batchId = (int) $context['window']['batch']['id'];
        $identity = GranteeIdentityProfile::query()->where('grantee_id', $grantee->id)->first();

        if (! $identity?->isComplete()) {
            throw ValidationException::withMessages([
                'onboarding' => 'Complete identity onboarding (ID scan + liveness) before submitting requirements.',
            ]);
        }

        $validated = $request->validate([
            'id_frame' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'id_face_crop' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'id_back' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'qr_payload' => ['required', 'string', 'max:2000'],
            'face_descriptor' => ['required', 'array', 'size:128'],
            'face_descriptor.*' => ['required', 'numeric'],
            'face_quality_score' => ['required', 'numeric', 'min:0', 'max:1'],
            'distance_vs_reference' => ['required', 'numeric', 'min:0'],
            'distance_vs_onboarding_selfie' => ['required', 'numeric', 'min:0'],
            'consent_accepted' => ['accepted'],
            'precheck_accepted' => ['accepted'],
        ]);

        if (! $qr->isValid($validated['qr_payload'])) {
            throw ValidationException::withMessages([
                'qr_payload' => 'School ID QR code must resolve to the TCC registrar domain.',
            ]);
        }

        try {
            $ocrResult = $ocr->extractText($validated['id_frame']);
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages(['id_frame' => $exception->getMessage()]);
        }

        $expected = $truth->expectedIdentity($grantee, $request->user()->kycProfile);
        $match = $ocr->matchAgainstExpected($ocrResult, $expected);
        if (! $match['ok']) {
            throw ValidationException::withMessages($match['errors']);
        }

        $threshold = (float) config('services.identity.face_match_threshold', 0.5);
        $vsReference = (float) $validated['distance_vs_reference'];
        $vsSelfie = (float) $validated['distance_vs_onboarding_selfie'];
        if ($vsReference >= $threshold || $vsSelfie >= $threshold) {
            throw ValidationException::withMessages([
                'face_match' => 'Submission ID face does not match onboarding reference photos. Retake the ID scan.',
            ]);
        }

        $quality = (float) $validated['face_quality_score'];
        $manualReview = $quality < 0.7;

        $facePath = IdentityPhotoStorage::storeNamed(
            $validated['id_face_crop'],
            $grantee->id,
            'id_scan_submission.jpg',
        );
        $framePath = $validated['id_frame']->store('submissions', 'public');
        $backPath = isset($validated['id_back']) ? $validated['id_back']->store('submissions', 'public') : null;

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
                'original_name' => 'id_scan_submission.jpg',
                'stored_path' => $facePath,
                'mime_type' => 'image/jpeg',
                'file_size' => $validated['id_face_crop']->getSize(),
                'secondary_original_name' => $backPath ? $validated['id_back']->getClientOriginalName() : 'id_frame.jpg',
                'secondary_stored_path' => $backPath ?: $framePath,
                'secondary_mime_type' => $backPath ? $validated['id_back']->getMimeType() : $validated['id_frame']->getMimeType(),
                'secondary_file_size' => $backPath ? $validated['id_back']->getSize() : $validated['id_frame']->getSize(),
                'status' => 'pending_review',
                'risk_level' => $manualReview ? 'medium' : 'low',
                'face_descriptor_payload' => array_map('floatval', $validated['face_descriptor']),
                'face_quality_score' => $quality,
                'identity_review_required' => $manualReview,
                'identity_review_reason' => $manualReview ? 'School ID face quality is below 0.70.' : null,
                'metadata_payload' => [
                    'qr_payload' => $validated['qr_payload'],
                    'ocr' => [
                        'provider' => $ocrResult['provider'],
                        'extracted_name' => $match['extracted_name'],
                        'extracted_student_id' => $match['extracted_student_id'],
                    ],
                    'face_distances' => [
                        'vs_id_reference' => $vsReference,
                        'vs_onboarding_selfie' => $vsSelfie,
                    ],
                    'frame_path' => $framePath,
                    'authenticity' => 'stubbed', // TODO: Pillow moiré via AUTHENTICITY_SERVICE_URL
                ],
            ],
        );

        $this->audit($request, 'school_id_live_scan_uploaded', $submission, [
            'quality' => $quality,
            'vs_reference' => $vsReference,
            'vs_onboarding_selfie' => $vsSelfie,
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

        $slotKey = (string) $request->input('slot_key');
        $isSpecimen = $slotKey === self::SPECIMEN_SIGNATURES_SLOT;

        $validated = $request->validate([
            'slot_key' => [
                'required',
                Rule::in([self::COURSE_HISTORY_SLOT, self::GRADE_SLIP_SLOT, self::SPECIMEN_SIGNATURES_SLOT]),
            ],
            'file' => [
                'required',
                'file',
                $isSpecimen ? 'mimes:jpg,jpeg,png,webp' : 'mimes:pdf',
                $isSpecimen ? 'max:10240' : 'max:20480',
            ],
        ]);

        $file = $validated['file'];
        $slotKey = $validated['slot_key'];
        $path = $file->store('submissions', 'public');
        $label = match ($slotKey) {
            self::COURSE_HISTORY_SLOT => 'Course History',
            self::GRADE_SLIP_SLOT => 'Grade Slip',
            self::SPECIMEN_SIGNATURES_SLOT => '3 Specimen Signatures',
            default => 'Requirement',
        };

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
            'distances' => ['required', 'array'],
            'distances.vs_submission_id' => ['required', 'numeric', 'min:0'],
            'distances.vs_id_reference' => ['required', 'numeric', 'min:0'],
            'distances.vs_onboarding_selfie' => ['required', 'numeric', 'min:0'],
            'confidence_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'consent_accepted' => ['accepted'],
            'liveness_confirmed' => ['accepted'],
            'selfie' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $threshold = (float) config('services.identity.face_match_threshold', 0.5);
        $distances = [
            'vs_submission_id' => (float) $validated['distances']['vs_submission_id'],
            'vs_id_reference' => (float) $validated['distances']['vs_id_reference'],
            'vs_onboarding_selfie' => (float) $validated['distances']['vs_onboarding_selfie'],
        ];
        $allPass = collect($distances)->every(fn (float $d) => $d < $threshold);
        $manualReview = ! $allPass || $validated['result'] !== 'match';
        $checkedAt = now();

        $selfiePath = IdentityPhotoStorage::storeNamed(
            $validated['selfie'],
            $grantee->id,
            'submission_selfie.jpg',
        );

        $check = RequirementIdentityCheck::create([
            'user_id' => $request->user()->id,
            'grantee_id' => $grantee->id,
            'batch_id' => $batchId,
            'document_submission_id' => $schoolId->id,
            'challenge_sequence' => $validated['challenge_sequence'],
            'result' => $manualReview ? 'no_match' : 'match',
            'distance' => $validated['distance'],
            'distances' => $distances,
            'selfie_path' => $selfiePath,
            'liveness_confirmed' => true,
            'confidence_score' => $validated['confidence_score'] ?? null,
            'manual_review_required' => $manualReview,
            'consent_accepted_at' => $checkedAt,
            'checked_at' => $checkedAt,
            'ip_address' => $request->ip(),
        ]);

        if ($manualReview) {
            $schoolId->update([
                'identity_review_required' => true,
                'identity_review_reason' => 'Submission liveness failed one or more face matches — flagged for manual review.',
                'risk_level' => 'high',
            ]);
        }

        $this->audit($request, 'identity_check_logged', $schoolId, [
            'result' => $check->result,
            'distances' => $distances,
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

        foreach ([
            self::SCHOOL_ID_SLOT,
            self::COURSE_HISTORY_SLOT,
            self::GRADE_SLIP_SLOT,
            self::SPECIMEN_SIGNATURES_SLOT,
        ] as $slot) {
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

        ProcessRequirementSubmissionPipeline::dispatch(
            $grantee->id,
            $batchId,
            (bool) $check->manual_review_required,
        );

        AuditLog::create([
            'actor' => $request->user()->name,
            'role' => 'Student',
            'action' => 'requirements_confirmed',
            'module' => 'Requirements Submission',
            'target' => "Grantee #{$grantee->id}",
            'context' => [
                'batch_id' => $batchId,
                'identity_result' => $check->result,
                'pipeline' => 'queued',
            ],
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'grantee' => $this->presentGrantee($grantee->fresh()),
            'identity_check' => $this->presentIdentityCheck($check),
        ]);
    }

    private function studentContext(Request $request, BatchWindowService $windows, bool $requireOpen = true): array
    {
        $user = $request->user();
        if ($user->account_status !== 'active') {
            throw ValidationException::withMessages([
                'account_status' => 'Complete KYC and identity onboarding before accessing the Requirement Vault.',
            ]);
        }

        $window = $windows->windowForStudent($user);
        if ($requireOpen && ! $window['open']) {
            throw ValidationException::withMessages(['submission_window' => $window['message']]);
        }

        $grantee = Grantee::query()
            ->where('user_id', $user->id)
            ->orWhere('student_id', $user->student_id)
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
            $label = match ($slotKey) {
                self::SCHOOL_ID_SLOT => 'School ID',
                self::COURSE_HISTORY_SLOT => 'Course History',
                self::GRADE_SLIP_SLOT => 'Grade Slip',
                self::SPECIMEN_SIGNATURES_SLOT => '3 Specimen Signatures',
                default => 'required',
            };

            throw ValidationException::withMessages([
                'slot_key' => "Complete the {$label} slot before continuing.",
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
            'distances' => $check->distances,
            'selfie_url' => IdentityPhotoStorage::url($check->selfie_path),
            'liveness_confirmed' => (bool) $check->liveness_confirmed,
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
