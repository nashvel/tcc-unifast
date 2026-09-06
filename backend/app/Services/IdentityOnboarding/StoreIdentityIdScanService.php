<?php

namespace App\Services\IdentityOnboarding;

use App\Models\AuditLog;
use App\Models\Grantee;
use App\Models\GranteeIdentityProfile;
use App\Models\User;
use App\Services\IdCardOcrService;
use App\Services\MasterlistTruthService;
use App\Support\FaceDescriptorMath;
use App\Support\SecureUpload;
use App\Support\VaultFileStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreIdentityIdScanService
{
    /** @var list<string> */
    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        private readonly IdCardOcrService $ocr,
        private readonly MasterlistTruthService $truth,
    ) {}

    public function assertCanScan(User $user, string $message): void
    {
        if (! in_array($user->account_status, ['pending_identity', 'identity_verified', 'active'], true)) {
            throw ValidationException::withMessages([
                'account_status' => $message,
            ]);
        }
    }

    /**
     * @return array{ok: true, extracted_name: mixed, extracted_student_id: mixed, ocr_provider: mixed}
     */
    public function validateFront(User $user, Grantee $grantee, UploadedFile $idFrame): array
    {
        SecureUpload::assertAllowedMime($idFrame, self::IMAGE_MIMES, 'id_frame');

        $front = $this->assertFrontOcrMatches($idFrame, $user, $grantee);

        return [
            'ok' => true,
            'extracted_name' => $front['match']['extracted_name'],
            'extracted_student_id' => $front['match']['extracted_student_id'],
            'ocr_provider' => $front['ocr']['provider'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{profile: GranteeIdentityProfile, qr_found: bool, qr_payload: ?string, back_fields: array<string, mixed>}
     */
    public function store(User $user, Grantee $grantee, array $validated, ?string $ipAddress): array
    {
        SecureUpload::assertAllowedMime($validated['id_frame'], self::IMAGE_MIMES, 'id_frame');
        SecureUpload::assertAllowedMime($validated['id_back'], self::IMAGE_MIMES, 'id_back');
        SecureUpload::assertAllowedMime($validated['id_face_crop'], self::IMAGE_MIMES, 'id_face_crop');
        $referenceDescriptor = FaceDescriptorMath::normalize($validated['face_descriptor']);

        $front = $this->assertFrontOcrMatches($validated['id_frame'], $user, $grantee);
        $ocrResult = $front['ocr'];
        $match = $front['match'];

        try {
            $backOcr = $this->ocr->extractTextAllowEmpty($validated['id_back']);
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages([
                'id_back' => $this->backOcrUnavailableMessage($exception->getMessage()),
            ]);
        }

        $backFields = $this->ocr->parseBackFields((string) ($backOcr['text'] ?? ''));

        $quality = (float) $validated['face_quality_score'];
        if ($quality < 0.5) {
            throw ValidationException::withMessages([
                'face_quality_score' => 'ID face quality is too low. Retake the scan in better lighting.',
            ]);
        }

        $existingProfile = GranteeIdentityProfile::query()->where('grantee_id', $grantee->id)->first();
        $previousFace = $existingProfile?->id_reference_face_path;
        $previousFrame = data_get($existingProfile?->id_ocr_payload, 'frame_path');
        $previousBack = data_get($existingProfile?->id_ocr_payload, 'back_path');

        $facePath = VaultFileStorage::storeIdentity($validated['id_face_crop'], $grantee->id, 'id_reference_face');
        $framePath = VaultFileStorage::storeIdentity($validated['id_frame'], $grantee->id, 'id_onboarding_frame');
        $backPath = VaultFileStorage::storeIdentity($validated['id_back'], $grantee->id, 'id_onboarding_back');

        $this->deletePreviousIdentityPath($previousFace, $facePath);
        $this->deletePreviousIdentityPath($previousFrame, $framePath);
        $this->deletePreviousIdentityPath($previousBack, $backPath);

        $clientQr = isset($validated['qr_payload']) ? trim((string) $validated['qr_payload']) : '';
        $backQr = is_array($backOcr['qr'] ?? null) ? $backOcr['qr'] : $this->ocr->emptyQr();
        $frontQr = is_array($ocrResult['qr'] ?? null) ? $ocrResult['qr'] : $this->ocr->emptyQr();
        $decodedQr = ($backQr['found'] ?? false) ? (string) ($backQr['value'] ?? '') : '';
        if ($decodedQr === '' && ($frontQr['found'] ?? false)) {
            $decodedQr = (string) ($frontQr['value'] ?? '');
        }
        $qrPayload = $clientQr !== '' ? $clientQr : $decodedQr;

        $profile = GranteeIdentityProfile::updateOrCreate(
            ['grantee_id' => $grantee->id],
            [
                'user_id' => $user->id,
                'status' => 'pending_liveness',
                'id_reference_face_path' => $facePath,
                'id_reference_face_descriptor' => $referenceDescriptor,
                'id_qr_payload' => $qrPayload !== '' ? $qrPayload : null,
                'id_ocr_payload' => [
                    'provider' => $ocrResult['provider'],
                    'text' => $ocrResult['text'],
                    'extracted_name' => $match['extracted_name'],
                    'extracted_student_id' => $match['extracted_student_id'],
                    'frame_path' => $framePath,
                    'back_path' => $backPath,
                    'back_ocr' => [
                        'skipped' => false,
                        'provider' => $backOcr['provider'],
                        'text' => $backOcr['text'],
                        'text_empty' => $backOcr['text_empty'],
                        'warning' => $backOcr['warning'],
                        'qr' => $backQr,
                    ],
                    'back_fields' => $backFields,
                    'front_qr' => $frontQr,
                    'qr_found' => $qrPayload !== '',
                    'qr_deferred' => $qrPayload === '',
                    'authenticity_skipped' => true,
                ],
                'id_scan_completed_at' => now(),
                'last_id_scan_ip' => $ipAddress,
            ],
        );

        AuditLog::create([
            'actor' => $user->name,
            'role' => 'Student',
            'action' => 'onboarding_id_scan_passed',
            'module' => 'Identity Onboarding',
            'target' => "Grantee #{$grantee->id}",
            'context' => [
                'qr_required' => false,
                'qr_found' => $qrPayload !== '',
                'ocr_provider' => $ocrResult['provider'],
                'back_ocr_provider' => $backOcr['provider'],
                'back_ocr_text_empty' => $backOcr['text_empty'],
                'face_quality' => $quality,
                'sides' => ['front', 'back'],
            ],
            'ip_address' => $ipAddress,
        ]);

        return [
            'profile' => $profile,
            'qr_found' => $qrPayload !== '',
            'qr_payload' => $qrPayload !== '' ? mb_substr($qrPayload, 0, 120) : null,
            'back_fields' => $backFields,
        ];
    }

    private function deletePreviousIdentityPath(mixed $previousPath, string $newPath): void
    {
        if (is_string($previousPath) && $previousPath !== '' && $previousPath !== $newPath) {
            VaultFileStorage::deleteIfOwned($previousPath);
        }
    }

    /**
     * @return array{ocr: array<string, mixed>, match: array<string, mixed>}
     */
    private function assertFrontOcrMatches(UploadedFile $idFrame, User $user, Grantee $grantee): array
    {
        try {
            $ocrResult = $this->ocr->extractText($idFrame);
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages([
                'id_frame' => $this->frontOcrFailMessage($exception->getMessage()),
            ]);
        }

        $expected = $this->truth->expectedIdentity($grantee, $user->kycProfile);
        $match = $this->ocr->matchAgainstExpected($ocrResult, $expected);
        if (! $match['ok']) {
            Log::warning('identity_onboarding.id_scan_ocr_mismatch', [
                'user_id' => $user->id,
                'grantee_id' => $grantee->id,
                'expected_name' => $expected['full_name'],
                'expected_student_id' => $expected['student_id'],
                'extracted_name' => $match['extracted_name'],
                'extracted_student_id' => $match['extracted_student_id'],
                'ocr_provider' => $ocrResult['provider'] ?? null,
                'ocr_text_length' => strlen((string) ($ocrResult['text'] ?? '')),
                'ocr_digit_stream_length' => strlen(preg_replace('/\D+/', '', (string) ($ocrResult['text'] ?? '')) ?? ''),
                'ocr_text_snippet' => Str::limit(
                    preg_replace('/\s+/u', ' ', (string) ($ocrResult['text'] ?? '')) ?? '',
                    240,
                ),
                'errors' => $match['errors'],
            ]);
            throw ValidationException::withMessages($match['errors']);
        }

        return ['ocr' => $ocrResult, 'match' => $match];
    }

    private function frontOcrFailMessage(string $detail): string
    {
        if (stripos($detail, 'unavailable') !== false || stripos($detail, 'No OCR provider') !== false) {
            return 'Front of School ID: Local OCR (:8081) is unavailable. Start ocr-service, then retry.';
        }

        return 'Front of School ID: '.$detail;
    }

    private function backOcrUnavailableMessage(string $detail): string
    {
        if (stripos($detail, 'unavailable') !== false || stripos($detail, 'No OCR provider') !== false) {
            return 'Back of School ID: Local OCR (:8081) is unavailable. Start ocr-service, then retry. (This is not a Front OCR name mismatch.)';
        }

        return 'Back of School ID: '.$detail;
    }
}
