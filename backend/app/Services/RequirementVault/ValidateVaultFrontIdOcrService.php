<?php

namespace App\Services\RequirementVault;

use App\Models\Grantee;
use App\Models\GranteeIdentityProfile;
use App\Models\User;
use App\Services\IdCardOcrService;
use App\Services\MasterlistTruthService;
use App\Support\SecureUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ValidateVaultFrontIdOcrService
{
    /** @var list<string> */
    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        private readonly IdCardOcrService $ocr,
        private readonly MasterlistTruthService $truth,
    ) {}

    public function assertIdentityComplete(Grantee $grantee): void
    {
        $identity = GranteeIdentityProfile::query()->where('grantee_id', $grantee->id)->first();
        if (! $identity?->isComplete()) {
            throw ValidationException::withMessages([
                'onboarding' => 'Complete identity onboarding (ID scan + liveness) before submitting requirements.',
            ]);
        }
    }

    /**
     * @return array{ok: true, extracted_name: mixed, extracted_student_id: mixed, ocr_provider: mixed}
     */
    public function validate(User $user, Grantee $grantee, UploadedFile $idFrame): array
    {
        SecureUpload::assertAllowedMime($idFrame, self::IMAGE_MIMES, 'id_frame');

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

        return [
            'ok' => true,
            'extracted_name' => $match['extracted_name'],
            'extracted_student_id' => $match['extracted_student_id'],
            'ocr_provider' => $ocrResult['provider'] ?? null,
        ];
    }

    private function frontOcrFailMessage(string $detail): string
    {
        if (stripos($detail, 'unavailable') !== false || stripos($detail, 'No OCR provider') !== false) {
            return 'Front of School ID: Local OCR (:8001) is unavailable. Start ocr-service, then retry.';
        }

        return 'Front of School ID: '.$detail;
    }
}
