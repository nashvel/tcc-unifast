<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Grantee;
use App\Models\GranteeIdentityProfile;
use App\Services\IdentityOnboarding\StoreIdentityIdScanService;
use App\Services\StudentOnboardingNavigator;
use App\Support\FaceDescriptorMath;
use App\Support\SecureUpload;
use App\Support\VaultFileStorage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IdentityOnboardingController extends Controller
{
    /** @var list<string> */
    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public function show(Request $request, StudentOnboardingNavigator $navigator): JsonResponse
    {
        $user = $request->user();
        $grantee = $this->grantee($request);
        $next = $navigator->nextStep($user, $grantee);

        // Do not create an identity row until KYC has moved the account to pending_identity.
        $profile = null;
        if (in_array($user->account_status, ['pending_identity', 'identity_verified', 'active', 'pending_face_review'], true)) {
            $profile = GranteeIdentityProfile::query()->firstOrCreate(
                ['grantee_id' => $grantee->id],
                ['user_id' => $user->id, 'status' => 'pending_id_scan'],
            );
        } else {
            $profile = GranteeIdentityProfile::query()->where('grantee_id', $grantee->id)->first();
        }

        return response()->json([
            'data' => [
                'account_status' => $user->account_status,
                'identity' => $profile ? $this->present($profile) : null,
                // Navigator enforces KYC → ID scan → liveness (ignore stale profile alone).
                'next_step' => $next,
            ],
        ]);
    }

    /**
     * Front-only OCR gate: match name & student ID before the client collects the back.
     * Does not persist identity profile state — full id-scan still completes both sides.
     */
    public function validateFrontIdOcr(
        Request $request,
        StoreIdentityIdScanService $idScan,
    ): JsonResponse {
        $user = $request->user();
        $idScan->assertCanScan($user, 'Complete KYC validation before scanning your School ID.');

        $grantee = $this->grantee($request);
        $validated = $request->validate([
            'id_frame' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        return response()->json([
            'data' => $idScan->validateFront($user, $grantee, $validated['id_frame']),
        ]);
    }

    public function storeIdScan(
        Request $request,
        StoreIdentityIdScanService $idScan,
    ): JsonResponse {
        $user = $request->user();
        $idScan->assertCanScan($user, 'Complete KYC validation before scanning your School ID.');

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

        $result = $idScan->store($user, $grantee, $validated, $request->ip());

        return response()->json([
            'data' => [
                'identity' => $this->present($result['profile']),
                'next_step' => 'liveness',
                'qr_found' => $result['qr_found'],
                'qr_payload' => $result['qr_payload'],
                'back_fields' => $result['back_fields'],
            ],
        ]);
    }

    /**
     * Proxy local OCR service /health so the browser avoids CORS to :8081.
     */
    public function ocrHealth(): JsonResponse
    {
        $baseUrl = rtrim((string) config('services.ocr.url', 'http://127.0.0.1:8081'), '/');
        if ($baseUrl === '') {
            return response()->json([
                'data' => [
                    'ok' => false,
                    'status' => 'unconfigured',
                    'url' => null,
                    'message' => 'OCR_SERVICE_URL is not configured.',
                ],
            ]);
        }

        try {
            $response = Http::acceptJson()->timeout(5)->get($baseUrl.'/health');
        } catch (ConnectionException $exception) {
            return response()->json([
                'data' => [
                    'ok' => false,
                    'status' => 'unavailable',
                    'url' => $baseUrl,
                    'message' => 'Local OCR (:8081) is unavailable',
                    'detail' => $exception->getMessage(),
                ],
            ]);
        }

        if ($response->failed()) {
            return response()->json([
                'data' => [
                    'ok' => false,
                    'status' => 'error',
                    'url' => $baseUrl,
                    'message' => 'Local OCR (:8081) is unavailable',
                    'http_status' => $response->status(),
                ],
            ]);
        }

        $payload = $response->json() ?? [];

        return response()->json([
            'data' => [
                'ok' => true,
                'status' => (string) (data_get($payload, 'status') ?: 'healthy'),
                'url' => $baseUrl,
                'tesseract_available' => (bool) data_get($payload, 'tesseract_available', true),
                'message' => 'Local OCR is reachable',
            ],
        ]);
    }

    public function storeLiveness(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->account_status === 'pending_face_review') {
            throw ValidationException::withMessages([
                'account_status' => 'Under staff review (not blocked) — wait for a face-match decision before retrying liveness.',
            ]);
        }
        if (! in_array($user->account_status, ['pending_identity', 'identity_verified', 'active'], true)) {
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
        if ($profile->status === 'pending_face_review') {
            throw ValidationException::withMessages([
                'account_status' => 'Under staff review (not blocked) — wait for a face-match decision before retrying liveness.',
            ]);
        }

        $validated = $request->validate([
            'selfie' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'challenge_still_1' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'challenge_still_2' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'challenge_still_labels' => ['required', 'array', 'size:2'],
            'challenge_still_labels.*' => ['required', Rule::in(['blink', 'turn_left', 'turn_right'])],
            'challenge_sequence' => ['required', 'array', 'size:3'],
            'challenge_sequence.*' => ['required', Rule::in(['blink', 'turn_left', 'turn_right'])],
            'face_descriptor' => ['required', 'array', 'size:128'],
            'face_descriptor.*' => ['required', 'numeric'],
            // Client distance is ignored; accepted only for backward-compatible payloads.
            'distance' => ['nullable', 'numeric', 'min:0'],
            'liveness_confirmed' => ['accepted'],
        ]);

        SecureUpload::assertAllowedMime($validated['selfie'], self::IMAGE_MIMES, 'selfie');
        SecureUpload::assertAllowedMime($validated['challenge_still_1'], self::IMAGE_MIMES, 'challenge_still_1');
        SecureUpload::assertAllowedMime($validated['challenge_still_2'], self::IMAGE_MIMES, 'challenge_still_2');
        $liveDescriptor = FaceDescriptorMath::normalize($validated['face_descriptor']);
        $referenceDescriptor = FaceDescriptorMath::normalize(
            $profile->id_reference_face_descriptor,
            'id_reference_face_descriptor',
        );
        $distance = FaceDescriptorMath::euclidean($referenceDescriptor, $liveDescriptor);
        $zone = FaceDescriptorMath::classify($distance);

        $previousSelfie = $profile->onboarding_selfie_path;
        $selfiePath = VaultFileStorage::storeIdentity(
            $validated['selfie'],
            $grantee->id,
            'onboarding_selfie',
        );
        if (is_string($previousSelfie) && $previousSelfie !== '' && $previousSelfie !== $selfiePath) {
            VaultFileStorage::deleteIfOwned($previousSelfie);
        }

        $challengePaths = $this->storeChallengeStills(
            $profile,
            $grantee->id,
            $validated['challenge_still_1'],
            $validated['challenge_still_2'],
        );
        $challengeLabels = array_values($validated['challenge_still_labels']);

        if ($zone === FaceDescriptorMath::ZONE_MISMATCH) {
            // Hard mismatch: keep onboarding open for retry (do not block / identity_mismatch).
            $profile->update([
                'onboarding_selfie_path' => $selfiePath,
                'onboarding_selfie_descriptor' => $liveDescriptor,
                'onboarding_face_distance' => $distance,
                'onboarding_challenge_sequence' => $validated['challenge_sequence'],
                'liveness_challenge_1_path' => $challengePaths[0],
                'liveness_challenge_2_path' => $challengePaths[1],
                'liveness_challenge_labels' => $challengeLabels,
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
                    'zone' => $zone,
                    'pass_max' => FaceDescriptorMath::passMax(),
                    'review_max' => FaceDescriptorMath::reviewMax(),
                    'liveness_confirmed' => true,
                    'result' => 'no_match_retry',
                    'account_status' => $user->account_status,
                ],
                'ip_address' => $request->ip(),
            ]);

            throw ValidationException::withMessages([
                'face_descriptor' => 'Face did not match — try again. Live face does not match the School ID reference.',
            ]);
        }

        if ($zone === FaceDescriptorMath::ZONE_UNCERTAIN) {
            $profile->update([
                'status' => 'pending_face_review',
                'onboarding_selfie_path' => $selfiePath,
                'onboarding_selfie_descriptor' => $liveDescriptor,
                'onboarding_face_distance' => $distance,
                'onboarding_challenge_sequence' => $validated['challenge_sequence'],
                'liveness_challenge_1_path' => $challengePaths[0],
                'liveness_challenge_2_path' => $challengePaths[1],
                'liveness_challenge_labels' => $challengeLabels,
                'last_liveness_ip' => $request->ip(),
            ]);

            $user->forceFill(['account_status' => 'pending_face_review'])->save();
            $grantee->update(['status' => 'pending_face_review']);

            AuditLog::create([
                'actor' => $user->name,
                'role' => 'Student',
                'action' => 'onboarding_liveness_uncertain',
                'module' => 'Identity Onboarding',
                'target' => "Grantee #{$grantee->id}",
                'context' => [
                    'distance' => $distance,
                    'distance_source' => 'server',
                    'zone' => $zone,
                    'pass_max' => FaceDescriptorMath::passMax(),
                    'review_max' => FaceDescriptorMath::reviewMax(),
                    'liveness_confirmed' => true,
                    'result' => 'uncertain',
                    'account_status' => 'pending_face_review',
                ],
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'data' => [
                    'identity' => $this->present($profile->fresh()),
                    'account_status' => $user->account_status,
                    'face_zone' => $zone,
                    'onboarding_face_distance' => $distance,
                    'next_step' => 'face_review',
                    'message' => 'Uncertain face match — under staff review (not blocked). Staff will compare your ID photo and selfie. You can sign out and return later.',
                ],
            ]);
        }

        $profile->update([
            'status' => 'completed',
            'onboarding_selfie_path' => $selfiePath,
            'onboarding_selfie_descriptor' => $liveDescriptor,
            'onboarding_face_distance' => $distance,
            'onboarding_challenge_sequence' => $validated['challenge_sequence'],
            'liveness_challenge_1_path' => $challengePaths[0],
            'liveness_challenge_2_path' => $challengePaths[1],
            'liveness_challenge_labels' => $challengeLabels,
            'onboarding_completed_at' => now(),
            'last_liveness_ip' => $request->ip(),
        ]);

        // Identity proven — but NOT 'active' yet: the student still has no password.
        // OnboardingCredentialController promotes to 'active' (invariant I1).
        $user->forceFill(['account_status' => 'identity_verified'])->save();
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
                'zone' => $zone,
                'pass_max' => FaceDescriptorMath::passMax(),
                'review_max' => FaceDescriptorMath::reviewMax(),
                'liveness_confirmed' => true,
                'result' => 'match',
                'account_status' => 'identity_verified',
            ],
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'data' => [
                'identity' => $this->present($profile->fresh()),
                'account_status' => $user->fresh()->account_status,
                'face_zone' => $zone,
                'next_step' => 'credentials',
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
                'id_onboarding_frame_url' => is_string(data_get($profile->id_ocr_payload, 'frame_path'))
                    && data_get($profile->id_ocr_payload, 'frame_path') !== ''
                    ? VaultFileStorage::authIdentityUrl('id_onboarding_frame.jpg')
                    : null,
                'onboarding_selfie_url' => $profile->onboarding_selfie_path
                    ? VaultFileStorage::authIdentityUrl('onboarding_selfie.jpg')
                    : null,
            ],
        ]);
    }

    /**
     * Store the two review-only challenge stills; replace prior paths if present.
     *
     * @return array{0: string, 1: string}
     */
    private function storeChallengeStills(
        GranteeIdentityProfile $profile,
        int $granteeId,
        UploadedFile $still1,
        UploadedFile $still2,
    ): array {
        $path1 = VaultFileStorage::storeIdentity($still1, $granteeId, 'liveness_challenge_1');
        $path2 = VaultFileStorage::storeIdentity($still2, $granteeId, 'liveness_challenge_2');

        $previous1 = $profile->liveness_challenge_1_path;
        $previous2 = $profile->liveness_challenge_2_path;
        if (is_string($previous1) && $previous1 !== '' && $previous1 !== $path1) {
            VaultFileStorage::deleteIfOwned($previous1);
        }
        if (is_string($previous2) && $previous2 !== '' && $previous2 !== $path2) {
            VaultFileStorage::deleteIfOwned($previous2);
        }

        return [$path1, $path2];
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
        $framePath = data_get($profile->id_ocr_payload, 'frame_path');

        return [
            'status' => $profile->status,
            'id_scan_completed_at' => $profile->id_scan_completed_at,
            'onboarding_completed_at' => $profile->onboarding_completed_at,
            'id_reference_face_url' => $profile->id_reference_face_path
                ? VaultFileStorage::authIdentityUrl('id_reference_face.jpg')
                : null,
            'id_onboarding_frame_url' => is_string($framePath) && $framePath !== ''
                ? VaultFileStorage::authIdentityUrl('id_onboarding_frame.jpg')
                : null,
            'onboarding_selfie_url' => $profile->onboarding_selfie_path
                ? VaultFileStorage::authIdentityUrl('onboarding_selfie.jpg')
                : null,
            'onboarding_face_distance' => $profile->onboarding_face_distance,
            'qr_found' => (bool) data_get($profile->id_ocr_payload, 'qr_found', false),
        ];
    }
}
