<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Grantee;
use App\Models\GranteeIdentityProfile;
use App\Services\IdCardOcrService;
use App\Services\MasterlistTruthService;
use App\Services\TccRegistrarQrService;
use App\Support\IdentityPhotoStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IdentityOnboardingController extends Controller
{
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
        TccRegistrarQrService $qr,
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
            'id_face_crop' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'qr_payload' => ['required', 'string', 'max:2000'],
            'face_quality_score' => ['required', 'numeric', 'min:0', 'max:1'],
            'authenticity_skipped' => ['nullable', 'boolean'],
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

        $expected = $truth->expectedIdentity($grantee, $user->kycProfile);
        $match = $ocr->matchAgainstExpected($ocrResult, $expected);
        if (! $match['ok']) {
            throw ValidationException::withMessages($match['errors']);
        }

        $quality = (float) $validated['face_quality_score'];
        if ($quality < 0.5) {
            throw ValidationException::withMessages([
                'face_quality_score' => 'ID face quality is too low. Retake the scan in better lighting.',
            ]);
        }

        $facePath = IdentityPhotoStorage::storeNamed(
            $validated['id_face_crop'],
            $grantee->id,
            'id_reference_face.jpg',
        );

        // Full frame kept for audit; Pillow/moire analysis is queued separately when available.
        $framePath = IdentityPhotoStorage::storeNamed(
            $validated['id_frame'],
            $grantee->id,
            'id_onboarding_frame.jpg',
        );

        $profile = GranteeIdentityProfile::updateOrCreate(
            ['grantee_id' => $grantee->id],
            [
                'user_id' => $user->id,
                'status' => 'pending_liveness',
                'id_reference_face_path' => $facePath,
                'id_qr_payload' => $validated['qr_payload'],
                'id_ocr_payload' => [
                    'provider' => $ocrResult['provider'],
                    'text' => $ocrResult['text'],
                    'extracted_name' => $match['extracted_name'],
                    'extracted_student_id' => $match['extracted_student_id'],
                    'frame_path' => $framePath,
                    'authenticity_skipped' => (bool) ($validated['authenticity_skipped'] ?? true),
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
                'qr_valid' => true,
                'ocr_provider' => $ocrResult['provider'],
                'face_quality' => $quality,
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
        if (! $profile || ! $profile->id_reference_face_path) {
            throw ValidationException::withMessages([
                'id_scan' => 'Complete the School ID scan before the liveness challenge.',
            ]);
        }

        $validated = $request->validate([
            'selfie' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'challenge_sequence' => ['required', 'array', 'size:3'],
            'challenge_sequence.*' => ['required', Rule::in(['blink', 'turn_left', 'turn_right'])],
            'distance' => ['required', 'numeric', 'min:0'],
            'liveness_confirmed' => ['accepted'],
        ]);

        $distance = (float) $validated['distance'];
        $matched = $distance < 0.5;

        $selfiePath = IdentityPhotoStorage::storeNamed(
            $validated['selfie'],
            $grantee->id,
            'onboarding_selfie.jpg',
        );

        if (! $matched) {
            $profile->update([
                'onboarding_selfie_path' => $selfiePath,
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
                    'liveness_confirmed' => true,
                    'result' => 'no_match',
                ],
                'ip_address' => $request->ip(),
            ]);

            $user->forceFill(['account_status' => 'blocked'])->save();
            $grantee->update(['status' => 'identity_mismatch']);

            throw ValidationException::withMessages([
                'distance' => 'Live face does not match the School ID reference. Account blocked for review.',
            ]);
        }

        $profile->update([
            'status' => 'completed',
            'onboarding_selfie_path' => $selfiePath,
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
        if (! $profile?->id_reference_face_path || ! Storage::disk('public')->exists($profile->id_reference_face_path)) {
            throw ValidationException::withMessages(['reference' => 'Onboarding ID reference face is missing.']);
        }

        return response()->json([
            'data' => [
                'id_reference_face_url' => IdentityPhotoStorage::url($profile->id_reference_face_path),
                'onboarding_selfie_url' => IdentityPhotoStorage::url($profile->onboarding_selfie_path),
            ],
        ]);
    }

    private function grantee(Request $request): Grantee
    {
        return Grantee::query()
            ->where('user_id', $request->user()->id)
            ->orWhere('student_id', $request->user()->student_id)
            ->firstOrFail();
    }

    private function present(GranteeIdentityProfile $profile): array
    {
        return [
            'status' => $profile->status,
            'id_scan_completed_at' => $profile->id_scan_completed_at,
            'onboarding_completed_at' => $profile->onboarding_completed_at,
            'id_reference_face_url' => IdentityPhotoStorage::url($profile->id_reference_face_path),
            'onboarding_selfie_url' => IdentityPhotoStorage::url($profile->onboarding_selfie_path),
            'onboarding_face_distance' => $profile->onboarding_face_distance,
        ];
    }
}
