<?php

namespace App\Services\RequirementVault;

use App\Jobs\ProcessRequirementSubmissionPipeline;
use App\Models\AuditLog;
use App\Models\DocumentSubmission;
use App\Models\Grantee;
use App\Models\GranteeIdentityProfile;
use App\Models\PolicySetting;
use App\Models\User;
use App\Services\IdCardOcrService;
use App\Services\MasterlistTruthService;
use App\Services\TccRegistrarQrService;
use App\Support\FaceDescriptorMath;
use App\Support\SecureUpload;
use App\Support\VaultFileStorage;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class StoreVaultSchoolIdService
{
    private const SCHOOL_ID_SLOT = 'school_id';

    /** @var list<string> */
    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * @param  array<string, mixed>  $validated
     */
    public function store(
        User $user,
        Grantee $grantee,
        int $batchId,
        GranteeIdentityProfile $identity,
        array $validated,
        IdCardOcrService $ocr,
        TccRegistrarQrService $qr,
        MasterlistTruthService $truth,
        ?string $ipAddress,
    ): DocumentSubmission {
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
        $threshold = FaceDescriptorMath::threshold();
        if ($vsReference >= $threshold || $vsSelfie >= $threshold) {
            throw ValidationException::withMessages([
                'face_match' => 'Submission ID face does not match onboarding reference photos. Retake the ID scan.',
            ]);
        }

        $front = $this->assertFrontOcrMatches(
            $validated['id_frame'],
            $user,
            $grantee,
            $ocr,
            $truth,
        );
        $ocrResult = $front['ocr'];
        $match = $front['match'];

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
        $manualReview = $quality < 0.7;

        $facePath = VaultFileStorage::storeIdentity(
            $validated['id_face_crop'],
            $grantee->id,
            'id_scan_submission',
        );
        $framePath = VaultFileStorage::storeDocument($validated['id_frame'], $grantee->id, $batchId);
        $backPath = VaultFileStorage::storeDocument($validated['id_back'], $grantee->id, $batchId);

        $prior = DocumentSubmission::query()
            ->where('student_id', $user->student_id)
            ->where('batch_id', $batchId)
            ->where('slot_key', self::SCHOOL_ID_SLOT)
            ->first();
        $slotStatus = (! $prior && $this->packageAlreadySentToStaff($grantee))
            ? 'pending_review'
            : 'draft';

        $submission = DocumentSubmission::updateOrCreate(
            [
                'student_id' => $user->student_id,
                'batch_id' => $batchId,
                'slot_key' => self::SCHOOL_ID_SLOT,
            ],
            [
                'grantee_id' => $grantee->id,
                'student_name' => $user->name,
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
                'ocr_payload' => [
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
                ],
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
                    'authenticity' => 'disabled',
                ],
            ],
        );

        if ($slotStatus === 'pending_review') {
            ProcessRequirementSubmissionPipeline::dispatch($grantee->id, $batchId, false);
        }

        AuditLog::create([
            'actor' => $user->name,
            'role' => 'Student',
            'action' => 'school_id_live_scan_uploaded',
            'module' => 'Requirements Submission',
            'target' => "Submission #{$submission->id}",
            'context' => [
                'quality' => $quality,
                'vs_reference' => $vsReference,
                'vs_onboarding_selfie' => $vsSelfie,
                'manual_review_required' => $manualReview,
                'status' => $slotStatus,
                'qr_found' => $qrFound,
                'qr_valid' => $qrValid,
                'academic_year_match' => $academicYearMatch,
            ],
            'ip_address' => $ipAddress,
        ]);

        return $submission->fresh();
    }

    /**
     * @return array{ocr: array<string, mixed>, match: array<string, mixed>}
     */
    private function assertFrontOcrMatches(
        mixed $idFrame,
        User $user,
        Grantee $grantee,
        IdCardOcrService $ocr,
        MasterlistTruthService $truth,
    ): array {
        try {
            $ocrResult = $ocr->extractText($idFrame);
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages([
                'id_frame' => $this->frontOcrFailMessage($exception->getMessage()),
            ]);
        }

        $expected = $truth->expectedIdentity($grantee, $user->kycProfile);
        $match = $ocr->matchAgainstExpected($ocrResult, $expected);
        if (! $match['ok']) {
            Log::warning('requirement_vault.id_scan_ocr_mismatch', [
                'user_id' => $user->id,
                'grantee_id' => $grantee->id,
                'expected_name' => $expected['full_name'],
                'expected_student_id' => $expected['student_id'],
                'extracted_name' => $match['extracted_name'],
                'extracted_student_id' => $match['extracted_student_id'],
                'ocr_provider' => $ocrResult['provider'] ?? null,
                'errors' => $match['errors'],
            ]);
            throw ValidationException::withMessages($match['errors']);
        }

        return ['ocr' => $ocrResult, 'match' => $match];
    }

    private function frontOcrFailMessage(string $detail): string
    {
        if (stripos($detail, 'unavailable') !== false || stripos($detail, 'No OCR provider') !== false) {
            return 'Front of School ID: Local OCR (:8001) is unavailable. Start ocr-service, then retry.';
        }

        return 'Front of School ID: '.$detail;
    }

    private function packageAlreadySentToStaff(Grantee $grantee): bool
    {
        return in_array(
            $grantee->submission_status ?? 'not_submitted',
            ['docs_submitted', 'under_review', 'verified', 'resubmission_requested'],
            true,
        );
    }
}
