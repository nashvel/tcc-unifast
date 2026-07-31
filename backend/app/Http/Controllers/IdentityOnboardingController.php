<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Grantee;
use App\Models\GranteeIdentityProfile;
use App\Services\IdCardOcrService;
use App\Services\MasterlistTruthService;
use App\Support\FaceDescriptorMath;
use App\Support\SecureUpload;
use App\Support\VaultFileStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IdentityOnboardingController extends Controller
{
    /** @var list<string> */
    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public function show(Request $request): JsonResponse
    {
        $grantee = $this->grantee($request);
        $profile = GranteeIdentityProfile::query()->firstOrCreate(
            ['grantee_id' => $grantee->id],
            ['user_id' => $request->user()->id, 'status' => 'pending_id_scan'],
        );

        return response()->json([
            'data' => [
                'account_status' => $request->user()->account_status,
                'identity' => $this->present($profile),
                'next_step' => match ($profile->status) {
                    'pending_id_scan' => 'id_scan',
                    'pending_liveness' => 'liveness',
                    'completed' => 'done',
                    default => 'id_scan',
                },
            ],
        ]);
    }

    public function storeIdScan(
        Request $request,
        IdCardOcrService $ocr,
        MasterlistTruthService $truth,
    ): JsonResponse {
        $user = $request->user();
        if (! in_array($user->account_status, ['pending_identity', 'active'], true)) {
            throw ValidationException::withMessages([
                'account_status' => 'Complete KYC validation before scanning your School ID.',
            ]);
        }

        $grantee = $this->grantee($request);
        $validated = $request->validate([
            'id_frame' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'id_back' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'id_face_crop' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            // QR optional until pyzbar / registrar decode is ready — do not block onboarding.
            'qr_payload' => ['nullable', 'string', 'max:2000'],
            'face_descriptor' => ['required', 'array', 'size:128'],
            'face_descriptor.*' => ['required', 'numeric'],
            'face_quality_score' => ['required', 'numeric', 'min:0', 'max:1'],
            'authenticity_skipped' => ['nullable', 'boolean'],
        ]);

        SecureUpload::assertAllowedMime($validated['id_frame'], self::IMAGE_MIMES, 'id_frame');
        SecureUpload::assertAllowedMime($validated['id_back'], self::IMAGE_MIMES, 'id_back');
        SecureUpload::assertAllowedMime($validated['id_face_crop'], self::IMAGE_MIMES, 'id_face_crop');
        $referenceDescriptor = FaceDescriptorMath::normalize($validated['face_descriptor']);

        try {
            $ocrResult = $ocr->extractText($validated['id_frame']);
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages([
                'id_frame' => 'Front of School ID: '.$exception->getMessage(),
            ]);
        }

        $expected = $truth->expectedIdentity($grantee, $user->kycProfile);
        $match = $ocr->matchAgainstExpected($ocrResult, $expected);
        if (! $match['ok']) {
            throw ValidationException::withMessages($match['errors']);
        }

        // Back OCR always runs. Service down → hard fail. Empty/sparse text → accept + store.
        // QR from local OCR is best-effort and never required to pass.
        try {
            $backOcr = $ocr->extractTextAllowEmpty($validated['id_back']);
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages([
                'id_back' => 'Back of School ID: '.$exception->getMessage(),
            ]);
        }

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

        $facePath = VaultFileStorage::storeIdentity(
            $validated['id_face_crop'],
            $grantee->id,
            'id_reference_face',
        );

        $framePath = VaultFileStorage::storeIdentity(
            $validated['id_frame'],
            $grantee->id,
            'id_onboarding_frame',
        );

        $backPath = VaultFileStorage::storeIdentity(
            $validated['id_back'],
            $grantee->id,
            'id_onboarding_back',
        );

        if (is_string($previousFace) && $previousFace !== '' && $previousFace !== $facePath) {
            VaultFileStorage::deleteIfOwned($previousFace);
        }
        if (is_string($previousFrame) && $previousFrame !== '' && $previousFrame !== $framePath) {
            VaultFileStorage::deleteIfOwned($previousFrame);
        }
        if (is_string($previousBack) && $previousBack !== '' && $previousBack !== $backPath) {
            VaultFileStorage::deleteIfOwned($previousBack);
        }

        $clientQr = isset($validated['qr_payload']) ? trim((string) $validated['qr_payload']) : '';
        $backQr = is_array($backOcr['qr'] ?? null) ? $backOcr['qr'] : $ocr->emptyQr();
        $frontQr = is_array($ocrResult['qr'] ?? null) ? $ocrResult['qr'] : $ocr->emptyQr();
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
                    'front_qr' => $frontQr,
                    'qr_found' => $qrPayload !== '',
                    'qr_deferred' => $qrPayload === '',
                    'authenticity_skipped' => true,
                ],
                'id_scan_completed_at' => now(),
                'last_id_scan_ip' => $request->ip(),
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
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'data' => [
                'identity' => $this->present($profile),
                'next_step' => 'liveness',
            ],
        ]);
    }

    public function storeLiveness(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! in_array($user->account_status, ['pending_identity', 'active'], true)) {
            throw ValidationException::withMessages([
                'account_status' => 'Complete KYC validation before the liveness challenge.',
            ]);
        }

        $grantee = $this->grantee($request);
        $profile = GranteeIdentityProfile::query()->where('grantee_id', $grantee->id)->first();
        if (! $profile || ! $profile->id_reference_face_path || ! is_array($profile->id_reference_face_descriptor)) {
            throw ValidationException::withMessages([
                'id_scan' => 'Complete the School ID scan before the liveness challenge.',
            ]);
        }

        $validated = $request->validate([
            'selfie' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'challenge_sequence' => ['required', 'array', 'size:3'],
            'challenge_sequence.*' => ['required', Rule::in(['blink', 'turn_left', 'turn_right'])],
            'face_descriptor' => ['required', 'array', 'size:128'],
            'face_descriptor.*' => ['required', 'numeric'],
            // Client distance is ignored; accepted only for backward-compatible payloads.
            'distance' => ['nullable', 'numeric', 'min:0'],
            'liveness_confirmed' => ['accepted'],
        ]);

        SecureUpload::assertAllowedMime($validated['selfie'], self::IMAGE_MIMES, 'selfie');
        $liveDescriptor = FaceDescriptorMath::normalize($validated['face_descriptor']);
        $referenceDescriptor = FaceDescriptorMath::normalize(
            $profile->id_reference_face_descriptor,
            'id_reference_face_descriptor',
        );
        $distance = FaceDescriptorMath::euclidean($referenceDescriptor, $liveDescriptor);
        $matched = $distance < FaceDescriptorMath::threshold();

        $previousSelfie = $profile->onboarding_selfie_path;
        $selfiePath = VaultFileStorage::storeIdentity(
            $validated['selfie'],
            $grantee->id,
            'onboarding_selfie',
        );
        if (is_string($previousSelfie) && $previousSelfie !== '' && $previousSelfie !== $selfiePath) {
            VaultFileStorage::deleteIfOwned($previousSelfie);
        }

        if (! $matched) {
            $profile->update([
                'onboarding_selfie_path' => $selfiePath,
                'onboarding_selfie_descriptor' => $liveDescriptor,
                'onboarding_face_distance' => $distance,
                'onboarding_challenge_sequence' => $validated['challenge_sequence'],
                'last_liveness_ip' => $request->ip(),
                'status' => 'pending_liveness',
            ]);

            AuditLog::create([
                'actor' => $user->name,
                'role' => 'Student',
                'action' => 'onboarding_liveness_failed',
                'module' => 'Identity Onboarding',
                'target' => "Grantee #{$grantee->id}",
                'context' => [
                    'distance' => $distance,
                    'distance_source' => 'server',
                    'liveness_confirmed' => true,
                    'result' => 'no_match',
                ],
                'ip_address' => $request->ip(),
            ]);

            $user->forceFill(['account_status' => 'blocked'])->save();
            $grantee->update(['status' => 'identity_mismatch']);

            throw ValidationException::withMessages([
                'face_descriptor' => 'Live face does not match the School ID reference. Account blocked for review.',
            ]);
        }

        $profile->update([
            'status' => 'completed',
            'onboarding_selfie_path' => $selfiePath,
            'onboarding_selfie_descriptor' => $liveDescriptor,
            'onboarding_face_distance' => $distance,
            'onboarding_challenge_sequence' => $validated['challenge_sequence'],
            'onboarding_completed_at' => now(),
            'last_liveness_ip' => $request->ip(),
        ]);

        $user->forceFill(['account_status' => 'active'])->save();
        $grantee->update(['status' => 'verified']);

        AuditLog::create([
            'actor' => $user->name,
            'role' => 'Student',
            'action' => 'onboarding_liveness_passed',
            'module' => 'Identity Onboarding',
            'target' => "Grantee #{$grantee->id}",
            'context' => [
                'distance' => $distance,
                'distance_source' => 'server',
                'liveness_confirmed' => true,
                'result' => 'match',
                'account_status' => 'active',
            ],
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'data' => [
                'identity' => $this->present($profile->fresh()),
                'account_status' => $user->account_status,
                'next_step' => 'done',
            ],
        ]);
    }

    public function referenceFace(Request $request): JsonResponse
    {
        $grantee = $this->grantee($request);
        $profile = GranteeIdentityProfile::query()->where('grantee_id', $grantee->id)->first();
        if (! $profile?->id_reference_face_path || ! VaultFileStorage::exists($profile->id_reference_face_path)) {
            throw ValidationException::withMessages(['reference' => 'Onboarding ID reference face is missing.']);
        }

        return response()->json([
            'data' => [
                'id_reference_face_url' => VaultFileStorage::authIdentityUrl('id_reference_face.jpg'),
                'onboarding_selfie_url' => $profile->onboarding_selfie_path
                    ? VaultFileStorage::authIdentityUrl('onboarding_selfie.jpg')
                    : null,
            ],
        ]);
    }

    private function grantee(Request $request): Grantee
    {
        $user = $request->user();
        $byUser = Grantee::query()->where('user_id', $user->id)->first();
        if ($byUser) {
            return $byUser;
        }

        if (! $user->student_id) {
            abort(404);
        }

        return Grantee::query()
            ->where('student_id', $user->student_id)
            ->whereNull('user_id')
            ->firstOrFail();
    }

    private function present(GranteeIdentityProfile $profile): array
    {
        return [
            'status' => $profile->status,
            'id_scan_completed_at' => $profile->id_scan_completed_at,
            'onboarding_completed_at' => $profile->onboarding_completed_at,
            'id_reference_face_url' => $profile->id_reference_face_path
                ? VaultFileStorage::authIdentityUrl('id_reference_face.jpg')
                : null,
            'onboarding_selfie_url' => $profile->onboarding_selfie_path
                ? VaultFileStorage::authIdentityUrl('onboarding_selfie.jpg')
                : null,
            'onboarding_face_distance' => $profile->onboarding_face_distance,
        ];
    }
}
