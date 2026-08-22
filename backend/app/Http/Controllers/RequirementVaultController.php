<?php

namespace App\Http\Controllers;

use App\Models\DocumentSubmission;
use App\Models\Grantee;
use App\Models\GranteeIdentityProfile;
use App\Models\User;
use App\Services\BatchWindowService;
use App\Services\IdCardOcrService;
use App\Services\MasterlistTruthService;
use App\Services\RequirementVault\ConfirmRequirementPackageService;
use App\Services\RequirementVault\RequirementVaultPresenter;
use App\Services\RequirementVault\ResubmitRequirementSlotService;
use App\Services\RequirementVault\StoreVaultDocumentSlotService;
use App\Services\RequirementVault\StoreVaultIdentityCheckService;
use App\Services\RequirementVault\StoreVaultSchoolIdService;
use App\Services\RequirementVault\ValidateVaultFrontIdOcrService;
use App\Services\TccRegistrarQrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    private const IMAGE_OR_PDF_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

    /** @var list<string> */
    private const PDF_MIMES = ['application/pdf'];

    public function show(Request $request, BatchWindowService $windows): JsonResponse
    {
        $context = $this->studentContext($request, $windows, false);

        return response()->json($this->presenter->show($context['window'], $context['grantee']));
    }

    public function validateFrontIdOcr(
        Request $request,
        BatchWindowService $windows,
        ValidateVaultFrontIdOcrService $validateFrontIdOcr,
    ): JsonResponse {
        $context = $this->studentContext($request, $windows);
        $grantee = $context['grantee'];
        $this->assertCanMutateVault($grantee, self::SCHOOL_ID_SLOT);
        $validateFrontIdOcr->assertIdentityComplete($grantee);

        $validated = $request->validate([
            'id_frame' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        return response()->json([
            'data' => $validateFrontIdOcr->validate($request->user(), $grantee, $validated['id_frame']),
        ]);
    }

    public function storeId(
        Request $request,
        BatchWindowService $windows,
        IdCardOcrService $ocr,
        TccRegistrarQrService $qr,
        MasterlistTruthService $truth,
        StoreVaultSchoolIdService $storeSchoolId,
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
            'id_back' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            // QR is best-effort for Slot 1 — store flags for staff; never hard-block submit.
            'qr_payload' => ['nullable', 'string', 'max:2000'],
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
        SecureUpload::assertAllowedMime($validated['id_back'], self::IMAGE_MIMES, 'id_back');

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
        $worstDistance = max($vsReference, $vsSelfie);
        $classification = FaceDescriptorMath::classify($worstDistance);

        if ($classification === FaceDescriptorMath::ZONE_MISMATCH) {
            $matchScore = round(max(0, 1 - $worstDistance) * 100);
            $reqScore = round(max(0, 1 - FaceDescriptorMath::reviewMax()) * 100);
            throw ValidationException::withMessages([
                'face_match' => sprintf(
                    'Face match failed. You scored %d%% (requires %d%% or higher). Retake the ID scan.',
                    $matchScore,
                    $reqScore
                ),
            ]);
        }

        $front = $this->assertFrontOcrMatches(
            $validated['id_frame'],
            $request->user(),
            $grantee,
            $batchId,
            $identity,
            $validated,
            $ocr,
            $qr,
            $truth,
        );
        $ocrResult = $front['ocr'];
        $match = $front['match'];

        // Back OCR always runs. Service down → hard fail. Empty/sparse text → accept + soft AY flags.
        try {
            $backOcr = $ocr->extractTextAllowEmpty($validated['id_back']);
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages([
                'id_back' => 'Back ID OCR is unavailable. Retry when the OCR service is online.',
            ]);
        }

        $backFields = $ocr->parseBackFields((string) ($backOcr['text'] ?? ''));
        $backQr = is_array($backOcr['qr'] ?? null) ? $backOcr['qr'] : $ocr->emptyQr();
        $frontQr = is_array($ocrResult['qr'] ?? null) ? $ocrResult['qr'] : $ocr->emptyQr();

        $clientQr = isset($validated['qr_payload']) ? trim((string) $validated['qr_payload']) : '';
        $decodedQr = ($backQr['found'] ?? false) ? (string) ($backQr['value'] ?? '') : '';
        if ($decodedQr === '' && ($frontQr['found'] ?? false)) {
            $decodedQr = (string) ($frontQr['value'] ?? '');
        }
        $qrPayload = $clientQr !== '' ? $clientQr : $decodedQr;
        $qrFound = $qrPayload !== '';
        $qrValid = $qrFound && $qr->isValid($qrPayload);
        $qrExtraction = $qrFound ? $qr->extract($qrPayload) : $qr->emptyExtraction();

        $expectedAy = PolicySetting::organizationAcademicYear();
        $ocrAyRaw = is_string($backFields['school_year'] ?? null) ? $backFields['school_year'] : null;
        $ocrAy = $ocr->normalizeSchoolYear($ocrAyRaw);
        $expectedAyNorm = $ocr->normalizeSchoolYear($expectedAy);
        $academicYearMatch = ($ocrAy !== null && $expectedAyNorm !== null)
            ? $ocrAy === $expectedAyNorm
            : null;

        $quality = (float) $validated['face_quality_score'];
        $manualReview = $quality < 0.7 || $classification === FaceDescriptorMath::ZONE_UNCERTAIN;

        $facePath = VaultFileStorage::storeIdentity(
            $validated['id_face_crop'],
            $grantee->id,
            'id_scan_submission',
        );
        $framePath = VaultFileStorage::storeDocument($validated['id_frame'], $grantee->id, $batchId);
        $backPath = VaultFileStorage::storeDocument($validated['id_back'], $grantee->id, $batchId);

        $prior = DocumentSubmission::query()
            ->where('student_id', $request->user()->student_id)
            ->where('batch_id', $batchId)
            ->where('slot_key', self::SCHOOL_ID_SLOT)
            ->first();
        // Legacy incomplete packages: never-submitted School ID goes straight to staff.
        $slotStatus = (! $prior && $this->packageAlreadySentToStaff($grantee))
            ? 'pending_review'
            : 'draft';

        $ocrPayload = [
            'provider' => $ocrResult['provider'],
            'extracted_name' => $match['extracted_name'],
            'extracted_student_id' => $match['extracted_student_id'],
            'back_fields' => $backFields,
            'qr_found' => $qrFound,
            'qr_valid' => $qrValid,
            'qr_extraction' => $qrExtraction,
            'academic_year_match' => $academicYearMatch,
            'academic_year_expected' => $expectedAyNorm,
            'academic_year_ocr' => $ocrAy,
        ];

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
                'secondary_original_name' => SecureUpload::sanitizeOriginalName(
                    $validated['id_back']->getClientOriginalName(),
                    'id_back',
                ),
                'secondary_stored_path' => $backPath,
                'secondary_mime_type' => SecureUpload::detectMime($validated['id_back']->getRealPath()),
                'secondary_file_size' => $validated['id_back']->getSize(),
                'status' => $slotStatus,
                'risk_level' => $manualReview ? 'medium' : 'low',
                'face_descriptor_payload' => $probe,
                'face_quality_score' => $quality,
                'identity_review_required' => $manualReview,
                'identity_review_reason' => $manualReview ? 'School ID face quality is below 0.70.' : null,
                'ocr_payload' => $ocrPayload,
                'metadata_payload' => [
                    'qr_payload' => $qrFound ? $qrPayload : null,
                    'qr_found' => $qrFound,
                    'qr_valid' => $qrValid,
                    'qr_extraction' => $qrExtraction,
                    'ocr' => [
                        'provider' => $ocrResult['provider'],
                        'extracted_name' => $match['extracted_name'],
                        'extracted_student_id' => $match['extracted_student_id'],
                    ],
                    'back_fields' => $backFields,
                    'back_ocr' => [
                        'provider' => $backOcr['provider'],
                        'text_empty' => $backOcr['text_empty'],
                        'warning' => $backOcr['warning'],
                        'qr' => $backQr,
                    ],
                    'academic_year_match' => $academicYearMatch,
                    'academic_year_expected' => $expectedAyNorm,
                    'academic_year_ocr' => $ocrAy,
                    'face_distances' => [
                        'vs_id_reference' => $vsReference,
                        'vs_onboarding_selfie' => $vsSelfie,
                    ],
                    'frame_path' => $framePath,
                    'back_path' => $backPath,
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
            'qr_found' => $qrFound,
            'qr_valid' => $qrValid,
            'academic_year_match' => $academicYearMatch,
        ]);

        return response()->json(['data' => $this->presentVaultDocument($submission)]);
    }

    public function storeDocument(Request $request, BatchWindowService $windows): JsonResponse
    {
        $context = $this->studentContext($request, $windows);
        $grantee = $context['grantee'];
        $batchId = (int) $context['window']['batch']['id'];

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
                $isSpecimen ? 'mimes:jpg,jpeg,png,webp,pdf' : 'mimes:pdf',
                'max:20480',
            ],
        ]);

        $file = $validated['file'];
        $slotKey = $validated['slot_key'];
        $allowed = $slotKey === self::SPECIMEN_SIGNATURES_SLOT ? self::IMAGE_OR_PDF_MIMES : self::PDF_MIMES;
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
            self::SPECIMEN_SIGNATURES_SLOT => 'ID Back-to-Back with Specimen',
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

        return response()->json(['data' => $this->presenter->document($submission)]);
    }

    /**
     * Resubmit a single returned slot after the student replaced the file (draft → pending_review).
     */
    public function resubmitSlot(
        Request $request,
        BatchWindowService $windows,
        ResubmitRequirementSlotService $resubmitRequirementSlot,
    ): JsonResponse {
        $context = $this->studentContext($request, $windows);
        $grantee = $context['grantee'];
        $batchId = (int) $context['window']['batch']['id'];

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
        $result = $resubmitRequirementSlot->resubmit(
            $request->user(),
            $grantee,
            $batchId,
            $validated['slot_key'],
            $request->ip(),
        );

        return response()->json([
            'data' => $this->presenter->document($result['submission']),
            'grantee' => $this->presenter->grantee($result['grantee']),
            'resubmitted' => true,
        ]);
    }

    /**
     * Optional submission liveness log. Does not promote drafts — confirm() is the submit gate.
     */
    public function storeIdentityCheck(
        Request $request,
        BatchWindowService $windows,
        StoreVaultIdentityCheckService $storeIdentityCheck,
    ): JsonResponse {
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
        $selfieDescriptor = FaceDescriptorMath::normalize(
            $identity->onboarding_selfie_descriptor,
            'onboarding_selfie_descriptor',
        );

        $match4 = FaceDescriptorMath::euclidean($live, $submissionDescriptor);
        $match5 = FaceDescriptorMath::euclidean($live, $selfieDescriptor);
        
        $worstDistance = max($match4, $match5);
        $classification = FaceDescriptorMath::classify($worstDistance);

        if ($classification === FaceDescriptorMath::ZONE_MISMATCH) {
            $matchScore = round(max(0, 1 - $worstDistance) * 100);
            $reqScore = round(max(0, 1 - FaceDescriptorMath::reviewMax()) * 100);
            throw ValidationException::withMessages([
                'face_match' => sprintf(
                    'Liveness check failed. You scored %d%% (requires %d%% or higher). Please retry under better lighting.',
                    $matchScore,
                    $reqScore
                ),
            ]);
        }

        $distances = [
            'vs_submission_id' => $match4,
            'vs_onboarding_selfie' => $match5,
        ];
        $manualReview = $classification === FaceDescriptorMath::ZONE_UNCERTAIN;
        $confidence = max(0, min(100, (1 - $worstDistance) * 100));
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
            'distance' => $worstDistance,
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
            'data' => $this->presenter->identityCheck($check),
            'grantee' => $this->presenter->grantee($grantee->fresh()),
            'submitted' => false,
        ]);
    }

    /**
     * Final submit gate: all slots + Slot 1 face bind + name consistency. No submission liveness required.
     */
    public function confirm(
        Request $request,
        BatchWindowService $windows,
        ConfirmRequirementPackageService $confirmPackage,
    ): JsonResponse {
        $context = $this->studentContext($request, $windows);
        $grantee = $context['grantee'];
        $this->assertCanMutateVault($grantee);
        $batchId = (int) $context['window']['batch']['id'];

        $result = $confirmPackage->confirm($request->user(), $grantee, $batchId, $request->ip());

        return response()->json([
            'grantee' => $this->presenter->grantee($result['grantee']),
            'identity_check' => $result['identity_check']
                ? $this->presenter->identityCheck($result['identity_check'])
                : null,
            'name_consistency' => $result['name_consistency'],
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
        $vsReference = FaceDescriptorMath::euclidean($probe, $reference);
        $vsSelfie = FaceDescriptorMath::euclidean($probe, $selfie);
        $worstDistance = max($vsReference, $vsSelfie);

        if (FaceDescriptorMath::isMismatch($worstDistance)) {
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

    private function ownedGrantee(User $user): ?Grantee
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
                    self::SPECIMEN_SIGNATURES_SLOT => 'ID Back-to-Back with Specimen',
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
                self::SPECIMEN_SIGNATURES_SLOT => 'ID Back-to-Back with Specimen',
                default => 'required',
            };

            throw ValidationException::withMessages([
                'slot_key' => "Complete the {$label} slot before continuing.",
            ]);
        }

        return $submission;
    }
}
