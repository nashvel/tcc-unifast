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
use App\Support\FaceDescriptorMath;
use App\Support\SecureUpload;
use App\Support\VaultFileStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RequirementVaultController extends Controller
{
    private const SCHOOL_ID_SLOT = 'school_id';

    private const COURSE_HISTORY_SLOT = 'course_history';

    private const GRADE_SLIP_SLOT = 'grade_slip';

    private const SPECIMEN_SIGNATURES_SLOT = 'specimen_signatures';

    /** @var list<string> */
    private const REQUIRED_SLOTS = [
        self::SCHOOL_ID_SLOT,
        self::COURSE_HISTORY_SLOT,
        self::GRADE_SLIP_SLOT,
        self::SPECIMEN_SIGNATURES_SLOT,
    ];

    /** @var list<string> */
    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    /** @var list<string> */
    private const PDF_MIMES = ['application/pdf'];

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
            'onboarding_refs' => ($identity && $context['grantee']) ? [
                'id_reference_face_url' => $identity->id_reference_face_path
                    ? VaultFileStorage::authIdentityUrl('id_reference_face.jpg')
                    : null,
                'onboarding_selfie_url' => $identity->onboarding_selfie_path
                    ? VaultFileStorage::authIdentityUrl('onboarding_selfie.jpg')
                    : null,
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
        $this->assertCanMutateVault($grantee, self::SCHOOL_ID_SLOT);
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
            // Client-reported distances are ignored; kept optional for older clients.
            'distance_vs_reference' => ['nullable', 'numeric', 'min:0'],
            'distance_vs_onboarding_selfie' => ['nullable', 'numeric', 'min:0'],
            'consent_accepted' => ['accepted'],
            'precheck_accepted' => ['accepted'],
        ]);

        SecureUpload::assertAllowedMime($validated['id_frame'], self::IMAGE_MIMES, 'id_frame');
        $faceMime = SecureUpload::assertAllowedMime($validated['id_face_crop'], self::IMAGE_MIMES, 'id_face_crop');
        if (isset($validated['id_back'])) {
            SecureUpload::assertAllowedMime($validated['id_back'], self::IMAGE_MIMES, 'id_back');
        }

        $probe = FaceDescriptorMath::normalize($validated['face_descriptor']);
        if (! is_array($identity->id_reference_face_descriptor) || ! is_array($identity->onboarding_selfie_descriptor)) {
            throw ValidationException::withMessages([
                'onboarding' => 'Onboarding face references are incomplete. Re-run identity onboarding.',
            ]);
        }
        $referenceDescriptor = FaceDescriptorMath::normalize(
            $identity->id_reference_face_descriptor,
            'id_reference_face_descriptor',
        );
        $selfieDescriptor = FaceDescriptorMath::normalize(
            $identity->onboarding_selfie_descriptor,
            'onboarding_selfie_descriptor',
        );
        $vsReference = FaceDescriptorMath::euclidean($probe, $referenceDescriptor);
        $vsSelfie = FaceDescriptorMath::euclidean($probe, $selfieDescriptor);
        $threshold = FaceDescriptorMath::threshold();
        if ($vsReference >= $threshold || $vsSelfie >= $threshold) {
            throw ValidationException::withMessages([
                'face_match' => 'Submission ID face does not match onboarding reference photos. Retake the ID scan.',
            ]);
        }

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

        $quality = (float) $validated['face_quality_score'];
        $manualReview = $quality < 0.7;

        $facePath = VaultFileStorage::storeIdentity(
            $validated['id_face_crop'],
            $grantee->id,
            'id_scan_submission',
        );
        $framePath = VaultFileStorage::storeDocument($validated['id_frame'], $grantee->id, $batchId);
        $backPath = isset($validated['id_back'])
            ? VaultFileStorage::storeDocument($validated['id_back'], $grantee->id, $batchId)
            : null;

        $prior = DocumentSubmission::query()
            ->where('student_id', $request->user()->student_id)
            ->where('batch_id', $batchId)
            ->where('slot_key', self::SCHOOL_ID_SLOT)
            ->first();
        // Legacy incomplete packages: never-submitted School ID goes straight to staff.
        $slotStatus = (! $prior && $this->packageAlreadySentToStaff($grantee))
            ? 'pending_review'
            : 'draft';

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
                'mime_type' => $faceMime,
                'file_size' => $validated['id_face_crop']->getSize(),
                'secondary_original_name' => $backPath
                    ? SecureUpload::sanitizeOriginalName($validated['id_back']->getClientOriginalName(), 'id_back')
                    : 'id_frame.jpg',
                'secondary_stored_path' => $backPath ?: $framePath,
                'secondary_mime_type' => $backPath
                    ? SecureUpload::detectMime($validated['id_back']->getRealPath())
                    : SecureUpload::detectMime($validated['id_frame']->getRealPath()),
                'secondary_file_size' => $backPath ? $validated['id_back']->getSize() : $validated['id_frame']->getSize(),
                'status' => $slotStatus,
                'risk_level' => $manualReview ? 'medium' : 'low',
                'face_descriptor_payload' => $probe,
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
                    'authenticity' => 'disabled', // Pillow moiré off until AUTHENTICITY_SERVICE_URL is set
                ],
            ],
        );

        if ($slotStatus === 'pending_review') {
            ProcessRequirementSubmissionPipeline::dispatch($grantee->id, $batchId, false);
        }

        $this->audit($request, 'school_id_live_scan_uploaded', $submission, [
            'quality' => $quality,
            'vs_reference' => $vsReference,
            'vs_onboarding_selfie' => $vsSelfie,
            'manual_review_required' => $manualReview,
            'status' => $slotStatus,
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
        $this->assertCanMutateVault($grantee, $slotKey !== '' ? $slotKey : null);
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
        $allowed = $slotKey === self::SPECIMEN_SIGNATURES_SLOT ? self::IMAGE_MIMES : self::PDF_MIMES;
        $detectedMime = SecureUpload::assertAllowedMime($file, $allowed, 'file');

        $existing = DocumentSubmission::query()
            ->where('student_id', $request->user()->student_id)
            ->where('batch_id', $batchId)
            ->where('slot_key', $slotKey)
            ->first();
        $previousPath = $existing?->stored_path;
        // First-time drafts stay private until confirm. Legacy never-submitted slots on an
        // already-sent package go straight to staff without unlocking other pending slots.
        $slotStatus = (! $existing && $this->packageAlreadySentToStaff($grantee))
            ? 'pending_review'
            : 'draft';

        $path = VaultFileStorage::storeDocument($file, $grantee->id, $batchId);
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
                'original_name' => SecureUpload::sanitizeOriginalName($file->getClientOriginalName(), $slotKey),
                'stored_path' => $path,
                'mime_type' => $detectedMime,
                'file_size' => $file->getSize(),
                'status' => $slotStatus,
                'risk_level' => 'low',
                'extracted_text' => null,
                'ocr_payload' => null,
                'metadata_payload' => null,
            ],
        );

        if (is_string($previousPath) && $previousPath !== '' && $previousPath !== $path) {
            VaultFileStorage::deleteIfOwned($previousPath);
        }

        if ($slotStatus === 'pending_review') {
            ProcessRequirementSubmissionPipeline::dispatch($grantee->id, $batchId, false);
        }

        $this->audit($request, 'requirement_uploaded', $submission, [
            'slot' => $slotKey,
            'status' => $slotStatus,
        ]);

        return response()->json(['data' => $this->presentVaultDocument($submission->fresh())]);
    }

    /**
     * Resubmit a single returned slot after the student replaced the file (draft → pending_review).
     */
    public function resubmitSlot(Request $request, BatchWindowService $windows): JsonResponse
    {
        $context = $this->studentContext($request, $windows);
        $grantee = $context['grantee'];
        $batchId = (int) $context['window']['batch']['id'];
        $studentId = $request->user()->student_id;

        $validated = $request->validate([
            'slot_key' => [
                'required',
                Rule::in([
                    self::SCHOOL_ID_SLOT,
                    self::COURSE_HISTORY_SLOT,
                    self::GRADE_SLIP_SLOT,
                    self::SPECIMEN_SIGNATURES_SLOT,
                ]),
            ],
        ]);
        $slotKey = $validated['slot_key'];

        $this->assertCanMutateVault($grantee, $slotKey);

        $submission = $this->requireSlot($studentId, $batchId, $slotKey);
        if ($submission->status !== 'draft') {
            throw ValidationException::withMessages([
                'slot_key' => 'Replace the returned document before resubmitting this slot.',
            ]);
        }

        if (($grantee->submission_status ?? '') !== 'resubmission_requested') {
            throw ValidationException::withMessages([
                'submission' => 'Single-slot resubmit is only available after staff requests a resubmission.',
            ]);
        }

        if ($slotKey === self::SCHOOL_ID_SLOT) {
            $this->assertSchoolIdFaceBound($grantee, $submission);
        }

        $submission->update(['status' => 'pending_review']);

        $stillOpen = DocumentSubmission::query()
            ->where('grantee_id', $grantee->id)
            ->where('batch_id', $batchId)
            ->whereIn('status', ['resubmission', 'draft'])
            ->exists();

        $grantee->update([
            'submission_status' => $stillOpen ? 'resubmission_requested' : 'docs_submitted',
            'submitted_at' => $grantee->submitted_at ?? now(),
        ]);

        ProcessRequirementSubmissionPipeline::dispatch(
            $grantee->id,
            $batchId,
            false,
        );

        AuditLog::create([
            'actor' => $request->user()->name,
            'role' => 'Student',
            'action' => 'requirement_slot_resubmitted',
            'module' => 'Requirements Submission',
            'target' => "Submission #{$submission->id}",
            'context' => [
                'batch_id' => $batchId,
                'slot_key' => $slotKey,
                'pipeline' => 'queued',
            ],
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'data' => $this->presentVaultDocument($submission->fresh()),
            'grantee' => $this->presentGrantee($grantee->fresh()),
            'resubmitted' => true,
        ]);
    }

    /**
     * Optional submission liveness log. Does not promote drafts — confirm() is the submit gate.
     */
    public function storeIdentityCheck(Request $request, BatchWindowService $windows): JsonResponse
    {
        $context = $this->studentContext($request, $windows);
        $grantee = $context['grantee'];
        $this->assertCanMutateVault($grantee);
        $batchId = (int) $context['window']['batch']['id'];
        $studentId = $request->user()->student_id;
        $schoolId = $this->requireSlot($studentId, $batchId, self::SCHOOL_ID_SLOT);

        $validated = $request->validate([
            'challenge_sequence' => ['required', 'array', 'size:3'],
            'challenge_sequence.*' => ['required', Rule::in(['blink', 'turn_left', 'turn_right'])],
            'face_descriptor' => ['required', 'array', 'size:128'],
            'face_descriptor.*' => ['required', 'numeric'],
            // Client match flags/distances are ignored; kept optional for older clients.
            'result' => ['nullable', Rule::in(['match', 'no_match'])],
            'distance' => ['nullable', 'numeric', 'min:0'],
            'distances' => ['nullable', 'array'],
            'distances.vs_submission_id' => ['nullable', 'numeric', 'min:0'],
            'distances.vs_id_reference' => ['nullable', 'numeric', 'min:0'],
            'distances.vs_onboarding_selfie' => ['nullable', 'numeric', 'min:0'],
            'confidence_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'consent_accepted' => ['accepted'],
            'liveness_confirmed' => ['accepted'],
            'selfie' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        SecureUpload::assertAllowedMime($validated['selfie'], self::IMAGE_MIMES, 'selfie');

        $identity = GranteeIdentityProfile::query()->where('grantee_id', $grantee->id)->first();
        if (! is_array($schoolId->face_descriptor_payload)
            || ! is_array($identity?->id_reference_face_descriptor)
            || ! is_array($identity?->onboarding_selfie_descriptor)) {
            throw ValidationException::withMessages([
                'face_descriptor' => 'Stored face references are missing. Re-scan School ID and complete onboarding.',
            ]);
        }

        $live = FaceDescriptorMath::normalize($validated['face_descriptor']);
        $submissionDescriptor = FaceDescriptorMath::normalize(
            $schoolId->face_descriptor_payload,
            'submission_face_descriptor',
        );
        $referenceDescriptor = FaceDescriptorMath::normalize(
            $identity->id_reference_face_descriptor,
            'id_reference_face_descriptor',
        );
        $selfieDescriptor = FaceDescriptorMath::normalize(
            $identity->onboarding_selfie_descriptor,
            'onboarding_selfie_descriptor',
        );

        $threshold = FaceDescriptorMath::threshold();
        $distances = [
            'vs_submission_id' => FaceDescriptorMath::euclidean($submissionDescriptor, $live),
            'vs_id_reference' => FaceDescriptorMath::euclidean($referenceDescriptor, $live),
            'vs_onboarding_selfie' => FaceDescriptorMath::euclidean($selfieDescriptor, $live),
        ];
        $allPass = collect($distances)->every(fn (float $d) => $d < $threshold);
        $manualReview = ! $allPass;
        $serverDistance = max($distances);
        $confidence = max(0, min(100, (1 - $serverDistance) * 100));
        $checkedAt = now();

        $selfiePath = VaultFileStorage::storeIdentity(
            $validated['selfie'],
            $grantee->id,
            'submission_selfie',
        );

        $check = RequirementIdentityCheck::create([
            'user_id' => $request->user()->id,
            'grantee_id' => $grantee->id,
            'batch_id' => $batchId,
            'document_submission_id' => $schoolId->id,
            'challenge_sequence' => $validated['challenge_sequence'],
            'result' => $manualReview ? 'no_match' : 'match',
            'distance' => $serverDistance,
            'distances' => $distances,
            'selfie_path' => $selfiePath,
            'liveness_confirmed' => true,
            'confidence_score' => $confidence,
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
            'distance_source' => 'server',
            'manual_review_required' => $manualReview,
        ]);

        return response()->json([
            'data' => $this->presentIdentityCheck($check),
            'grantee' => $this->presentGrantee($grantee->fresh()),
            'submitted' => false,
        ]);
    }

    /**
     * Final submit gate: all slots + Slot 1 face bind + name consistency. No submission liveness required.
     */
    public function confirm(Request $request, BatchWindowService $windows): JsonResponse
    {
        $context = $this->studentContext($request, $windows);
        $grantee = $context['grantee'];
        $this->assertCanMutateVault($grantee);
        $batchId = (int) $context['window']['batch']['id'];
        $studentId = $request->user()->student_id;

        $status = $grantee->submission_status ?? 'not_submitted';
        if (in_array($status, ['docs_submitted', 'under_review', 'verified'], true)) {
            throw ValidationException::withMessages([
                'submission' => 'Requirements were already confirmed for this batch.',
            ]);
        }
        if ($status === 'resubmission_requested') {
            throw ValidationException::withMessages([
                'submission' => 'Staff requested a resubmission. Resubmit only returned document(s) — use Replace then Resubmit on each returned slot.',
            ]);
        }

        $missing = $this->missingRequiredSlotLabels($studentId, $batchId);
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'submission' => 'Submit all four documents to staff before confirming. Missing: '
                    .implode(', ', $missing).'.',
            ]);
        }

        $schoolId = $this->requireSlot($studentId, $batchId, self::SCHOOL_ID_SLOT);
        $this->assertSchoolIdFaceBound($grantee, $schoolId);
        $nameFlags = $this->assertNameConsistency($request, $grantee, $batchId, $schoolId);

        $check = RequirementIdentityCheck::query()
            ->where('grantee_id', $grantee->id)
            ->where('batch_id', $batchId)
            ->where('user_id', $request->user()->id)
            ->latest('checked_at')
            ->first();

        $identityFailed = (bool) ($check?->manual_review_required)
            || (bool) $schoolId->identity_review_required
            || ($nameFlags['gradeslip_mismatch'] ?? false);

        $grantee = $this->promoteDraftsAndQueuePipeline($request, $grantee, $batchId, $check, $identityFailed);

        return response()->json([
            'grantee' => $this->presentGrantee($grantee),
            'identity_check' => $check ? $this->presentIdentityCheck($check) : null,
            'name_consistency' => $nameFlags,
            'submitted' => true,
        ]);
    }

    private function promoteDraftsAndQueuePipeline(
        Request $request,
        Grantee $grantee,
        int $batchId,
        ?RequirementIdentityCheck $check,
        bool $identityFailed = false,
    ): Grantee {
        // Promote student drafts into the staff validation queue, then run OCR/metadata.
        DocumentSubmission::query()
            ->where('grantee_id', $grantee->id)
            ->where('batch_id', $batchId)
            ->whereIn('status', ['draft', 'resubmission'])
            ->update(['status' => 'pending_review']);

        $grantee->update([
            'submission_status' => 'docs_submitted',
            'submitted_at' => now(),
        ]);

        ProcessRequirementSubmissionPipeline::dispatch(
            $grantee->id,
            $batchId,
            $identityFailed,
        );

        AuditLog::create([
            'actor' => $request->user()->name,
            'role' => 'Student',
            'action' => 'requirements_confirmed',
            'module' => 'Requirements Submission',
            'target' => "Grantee #{$grantee->id}",
            'context' => [
                'batch_id' => $batchId,
                'identity_result' => $check?->result,
                'pipeline' => 'queued',
                'via' => 'confirm',
                'identity_failed' => $identityFailed,
            ],
            'ip_address' => $request->ip(),
        ]);

        return $grantee->fresh();
    }

    private function assertSchoolIdFaceBound(Grantee $grantee, DocumentSubmission $schoolId): void
    {
        $identity = GranteeIdentityProfile::query()->where('grantee_id', $grantee->id)->first();
        if (! $identity?->isComplete()
            || ! is_array($schoolId->face_descriptor_payload)
            || ! is_array($identity->id_reference_face_descriptor)
            || ! is_array($identity->onboarding_selfie_descriptor)) {
            throw ValidationException::withMessages([
                'face_match' => 'School ID face bind is incomplete. Re-scan Slot 1 after finishing identity onboarding.',
            ]);
        }

        $probe = FaceDescriptorMath::normalize($schoolId->face_descriptor_payload, 'submission_face_descriptor');
        $reference = FaceDescriptorMath::normalize(
            $identity->id_reference_face_descriptor,
            'id_reference_face_descriptor',
        );
        $selfie = FaceDescriptorMath::normalize(
            $identity->onboarding_selfie_descriptor,
            'onboarding_selfie_descriptor',
        );
        $threshold = FaceDescriptorMath::threshold();
        $vsReference = FaceDescriptorMath::euclidean($probe, $reference);
        $vsSelfie = FaceDescriptorMath::euclidean($probe, $selfie);

        if ($vsReference >= $threshold || $vsSelfie >= $threshold) {
            throw ValidationException::withMessages([
                'face_match' => 'School ID face does not match onboarding reference photos. Re-scan Slot 1 before submitting.',
            ]);
        }
    }

    /**
     * Require profile ≈ masterlist/grantee ≈ school ID OCR name.
     * Grade slip name is best-effort: soft-flag when OCR text already exists; never block if missing.
     *
     * @return array{profile: string, grantee: string, school_id: ?string, grade_slip: ?string, gradeslip_mismatch: bool}
     */
    private function assertNameConsistency(
        Request $request,
        Grantee $grantee,
        int $batchId,
        DocumentSubmission $schoolId,
    ): array {
        $profileName = trim((string) $request->user()->name);
        $granteeName = trim((string) $grantee->full_name);

        if ($profileName === '' || $granteeName === '') {
            throw ValidationException::withMessages([
                'name_match' => 'Profile and masterlist names are required before submit.',
            ]);
        }

        if (! $this->namesLooselyMatch($profileName, $granteeName)) {
            throw ValidationException::withMessages([
                'name_match' => 'Your account name does not match the CHED masterlist / grantee name.',
            ]);
        }

        $identity = GranteeIdentityProfile::query()->where('grantee_id', $grantee->id)->first();
        $schoolIdName = data_get($schoolId->metadata_payload, 'ocr.extracted_name')
            ?: data_get($identity?->id_ocr_payload, 'extracted_name');
        $schoolIdName = is_string($schoolIdName) ? trim($schoolIdName) : null;
        if ($schoolIdName === '') {
            $schoolIdName = null;
        }

        if ($schoolIdName !== null && ! $this->namesLooselyMatch($profileName, $schoolIdName)) {
            throw ValidationException::withMessages([
                'name_match' => 'School ID OCR name does not match your profile / masterlist name. Re-scan Slot 1.',
            ]);
        }

        $gradeSlip = DocumentSubmission::query()
            ->where('student_id', $request->user()->student_id)
            ->where('batch_id', $batchId)
            ->where('slot_key', self::GRADE_SLIP_SLOT)
            ->first();

        [$gradeSlipName, $gradeslipMismatch] = $this->gradeSlipNameCheck($gradeSlip, $profileName);
        if ($gradeslipMismatch) {
            $gradeSlip?->update([
                'identity_review_required' => true,
                'identity_review_reason' => 'Grade slip name (from existing OCR/text) does not match profile / masterlist.',
                'risk_level' => 'medium',
            ]);
        }

        return [
            'profile' => $profileName,
            'grantee' => $granteeName,
            'school_id' => $schoolIdName,
            'grade_slip' => $gradeSlipName,
            'gradeslip_mismatch' => $gradeslipMismatch,
        ];
    }

    /**
     * Soft check only when draft already has OCR/extracted text. Missing text does not block submit.
     *
     * @return array{0: ?string, 1: bool}
     */
    private function gradeSlipNameCheck(?DocumentSubmission $doc, string $profileName): array
    {
        if (! $doc) {
            return [null, false];
        }

        $explicit = data_get($doc->metadata_payload, 'ocr.extracted_name')
            ?: data_get($doc->ocr_payload, 'extracted_name')
            ?: data_get($doc->ocr_payload, 'result.extracted_name');
        if (is_string($explicit) && trim($explicit) !== '') {
            $name = trim($explicit);

            return [$name, ! $this->namesLooselyMatch($profileName, $name)];
        }

        $text = trim((string) ($doc->extracted_text ?? ''));
        if ($text === '') {
            $text = trim((string) data_get($doc->ocr_payload, 'result.combined_text', ''));
        }
        if ($text === '') {
            return [null, false];
        }

        $haystack = $this->nameKey($text);
        $expected = $this->nameKey($profileName);
        if ($expected !== '' && str_contains($haystack, $expected)) {
            return [$profileName, false];
        }

        $parts = array_values(array_filter(explode(' ', $expected), fn (string $p) => strlen($p) > 1));
        if ($parts === []) {
            return [null, false];
        }
        $hits = count(array_filter($parts, fn (string $p) => str_contains($haystack, $p)));
        $ok = $hits >= max(2, (int) floor(count($parts) * 0.6));

        return [null, ! $ok];
    }

    private function namesLooselyMatch(string $left, string $right): bool
    {
        $expected = $this->nameKey($left);
        $candidate = $this->nameKey($right);
        if ($expected === '' || $candidate === '') {
            return false;
        }
        if ($expected === $candidate) {
            return true;
        }

        $expectedParts = array_values(array_filter(explode(' ', $expected)));
        $candidateParts = array_values(array_filter(explode(' ', $candidate)));
        if (count($expectedParts) < 2 || count($candidateParts) < 2) {
            return $expected === $candidate;
        }

        $overlap = count(array_intersect($expectedParts, $candidateParts));

        return $overlap >= max(2, (int) floor(count($expectedParts) * 0.6));
    }

    private function nameKey(string $value): string
    {
        return Str::of($value)->lower()->replaceMatches('/\s+/', ' ')->trim()->toString();
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

        $grantee = $this->ownedGrantee($user);

        if ($requireOpen && ! $grantee) {
            throw ValidationException::withMessages(['grantee' => 'Your grantee profile is not assigned.']);
        }

        return ['window' => $window, 'grantee' => $grantee];
    }

    private function ownedGrantee(\App\Models\User $user): ?Grantee
    {
        $byUser = Grantee::query()->where('user_id', $user->id)->first();
        if ($byUser) {
            return $byUser;
        }

        if (! $user->student_id) {
            return null;
        }

        // Fallback only for unlinked masterlist rows for this student — never another user's grantee.
        return Grantee::query()
            ->where('student_id', $user->student_id)
            ->whereNull('user_id')
            ->first();
    }

    private function assertCanMutateVault(Grantee $grantee, ?string $slotKey = null): void
    {
        $status = $grantee->submission_status ?? 'not_submitted';
        $packageLocked = in_array($status, ['docs_submitted', 'under_review', 'verified', 'resubmission_requested'], true);
        if (! $packageLocked) {
            return;
        }

        if ($slotKey) {
            $slot = DocumentSubmission::query()
                ->where('grantee_id', $grantee->id)
                ->where('batch_id', $grantee->batch_id)
                ->where('slot_key', $slotKey)
                ->first();

            // Returned slots, or drafts created by replacing a returned slot.
            if ($slot && $slot->status === 'resubmission') {
                return;
            }
            if ($slot && $slot->status === 'draft' && $status === 'resubmission_requested') {
                return;
            }
            // Legacy incomplete packages: allow filling never-submitted slots only.
            if (! $slot && $this->packageAlreadySentToStaff($grantee) && ! $this->packageHasAllRequiredSlots($grantee)) {
                return;
            }
        } else {
            $hasOpenResubmission = DocumentSubmission::query()
                ->where('grantee_id', $grantee->id)
                ->where('batch_id', $grantee->batch_id)
                ->whereIn('status', ['resubmission', 'draft'])
                ->exists();

            if ($status === 'resubmission_requested' && $hasOpenResubmission) {
                return;
            }
        }

        throw ValidationException::withMessages([
            'submission' => 'Requirements already submitted. Wait for staff to request a resubmission.',
        ]);
    }

    /**
     * @return list<string>
     */
    private function missingRequiredSlotLabels(string $studentId, int $batchId): array
    {
        $present = DocumentSubmission::query()
            ->where('student_id', $studentId)
            ->where('batch_id', $batchId)
            ->whereIn('slot_key', self::REQUIRED_SLOTS)
            ->pluck('slot_key')
            ->all();

        $missing = [];
        foreach (self::REQUIRED_SLOTS as $slotKey) {
            if (! in_array($slotKey, $present, true)) {
                $missing[] = match ($slotKey) {
                    self::SCHOOL_ID_SLOT => 'School ID',
                    self::COURSE_HISTORY_SLOT => 'Course History',
                    self::GRADE_SLIP_SLOT => 'Grade Slip',
                    self::SPECIMEN_SIGNATURES_SLOT => '3 Specimen Signatures',
                    default => $slotKey,
                };
            }
        }

        return $missing;
    }

    private function packageAlreadySentToStaff(Grantee $grantee): bool
    {
        return in_array(
            $grantee->submission_status ?? 'not_submitted',
            ['docs_submitted', 'under_review', 'verified', 'resubmission_requested'],
            true,
        );
    }

    private function packageHasAllRequiredSlots(Grantee $grantee): bool
    {
        $count = DocumentSubmission::query()
            ->where('grantee_id', $grantee->id)
            ->where('batch_id', $grantee->batch_id)
            ->whereIn('slot_key', self::REQUIRED_SLOTS)
            ->distinct()
            ->count('slot_key');

        return $count >= count(self::REQUIRED_SLOTS);
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
        $rows = DocumentSubmission::query()
            ->where('batch_id', $grantee->batch_id)
            ->where(function ($query) use ($grantee): void {
                $query->where('grantee_id', $grantee->id);
                if ($grantee->student_id) {
                    $query->orWhere('student_id', $grantee->student_id);
                }
            })
            ->orderBy('id')
            ->get();

        $slots = [];
        foreach ($rows as $item) {
            $slotKey = (string) $item->slot_key;
            if ($slotKey === '') {
                continue;
            }

            try {
                $slots[$slotKey] = $this->presentVaultDocument($item);
            } catch (\Throwable $exception) {
                report($exception);
                // Keep the slot visible even if encrypted face payload cannot be decoded.
                $slots[$slotKey] = [
                    'id' => $item->id,
                    'slot_key' => $slotKey,
                    'document_type' => $item->document_type,
                    'original_name' => $item->original_name,
                    'secondary_original_name' => $item->secondary_original_name,
                    'status' => $item->status,
                    'risk_level' => $item->risk_level,
                    'face_quality_score' => $item->face_quality_score,
                    'identity_review_required' => (bool) $item->identity_review_required,
                    'identity_review_reason' => $item->identity_review_reason,
                    'review_notes' => $item->review_notes,
                    'created_at' => $item->created_at,
                    'file_url' => VaultFileStorage::authStudentSubmissionUrl($item, 'primary'),
                    'secondary_file_url' => VaultFileStorage::authStudentSubmissionUrl($item, 'secondary'),
                    'face_descriptor' => null,
                ];
            }
        }

        return $slots;
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
        $faceDescriptor = null;
        if ($item->slot_key === self::SCHOOL_ID_SLOT) {
            try {
                $faceDescriptor = $item->face_descriptor_payload;
            } catch (\Throwable $exception) {
                report($exception);
                $faceDescriptor = null;
            }
        }

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
            'review_notes' => $item->review_notes,
            'created_at' => $item->created_at,
            'file_url' => VaultFileStorage::authStudentSubmissionUrl($item, 'primary'),
            'secondary_file_url' => VaultFileStorage::authStudentSubmissionUrl($item, 'secondary'),
            // Face descriptor stays owner-scoped (student vault only). Server re-verifies matches.
            'face_descriptor' => $faceDescriptor,
        ];
    }

    private function presentIdentityCheck(RequirementIdentityCheck $check): array
    {
        $selfieFilename = $check->selfie_path ? basename((string) $check->selfie_path) : null;

        return [
            'id' => $check->id,
            'challenge_sequence' => $check->challenge_sequence,
            'result' => $check->result,
            'distance' => $check->distance,
            'distances' => $check->distances,
            'selfie_url' => $selfieFilename
                ? VaultFileStorage::authIdentityUrl($selfieFilename)
                : null,
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
