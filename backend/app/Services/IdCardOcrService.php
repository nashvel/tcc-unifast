<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class IdCardOcrService
{
    private ?IdCardBackParser $backParser = null;

    private ?IdCardIdentityMatcher $identityMatcher = null;

    /**
     * Extract printable text from an ID card image via local OCR service (dev default).
     * OCR.space only when OCR_SPACE_API_KEY is explicitly set (not used in local free/dev phase).
     *
     * @return array{text: string, provider: string, raw: array<string, mixed>|null, qr: array{found: bool, value: ?string, type: ?string, engine: ?string}}
     */
    public function extractText(UploadedFile $file): array
    {
        $ocrSpaceKey = trim((string) config('services.identity.ocr_space_api_key'));
        if ($ocrSpaceKey !== '') {
            return $this->viaOcrSpace($file, $ocrSpaceKey);
        }

        return $this->viaLocalOcr($file);
    }

    /**
     * OCR for secondary images (e.g. ID back).
     * Service / provider failures throw (same as extractText). Empty or sparse text is success.
     *
     * @return array{text: string, provider: string, raw: array<string, mixed>|null, text_empty: bool, warning: ?string, qr: array{found: bool, value: ?string, type: ?string, engine: ?string}}
     */
    public function extractTextAllowEmpty(UploadedFile $file): array
    {
        $result = $this->extractText($file);
        $text = trim((string) ($result['text'] ?? ''));
        $qr = is_array($result['qr'] ?? null) ? $result['qr'] : $this->emptyQr();

        return [
            'text' => $text,
            'provider' => (string) ($result['provider'] ?? 'unknown'),
            'raw' => $result['raw'] ?? null,
            'text_empty' => $text === '',
            'warning' => $text === ''
                ? 'OCR service succeeded but found little or no readable text on this image.'
                : null,
            'qr' => $qr,
        ];
    }

    /**
     * @return array{found: bool, value: ?string, type: ?string, engine: ?string}
     */
    public function emptyQr(): array
    {
        return ['found' => false, 'value' => null, 'type' => null, 'engine' => null];
    }

    /**
     * @param  array<string, mixed>|null  $raw
     * @return array{found: bool, value: ?string, type: ?string, engine: ?string}
     */
    public function qrFromPayload(?array $raw): array
    {
        $found = (bool) data_get($raw, 'qr_code.found', false);
        $value = data_get($raw, 'qr_code.value');
        $value = is_string($value) ? trim($value) : null;

        return [
            'found' => $found && $value !== null && $value !== '',
            'value' => ($found && is_string($value) && $value !== '') ? $value : null,
            'type' => is_string(data_get($raw, 'qr_code.type')) ? (string) data_get($raw, 'qr_code.type') : null,
            'engine' => is_string(data_get($raw, 'qr_code.engine')) ? (string) data_get($raw, 'qr_code.engine') : null,
        ];
    }

    /**
     * Best-effort structured fields from School ID back OCR text.
     * Missing fields are null — never hard-fails.
     *
     * @return array{
     *     school_year: ?string,
     *     emergency_contact_name: ?string,
     *     emergency_contact_relationship: ?string,
     *     emergency_contact_phone: ?string
     * }
     */
    public function parseBackFields(string $text): array
    {
        return $this->backParser()->parseBackFields($text);
    }

    /**
     * Normalize academic / school year strings for soft comparison
     * (e.g. "SY 2026-2027", "2026–27", "2026/2027" → "2026-2027").
     */
    public function normalizeSchoolYear(?string $value): ?string
    {
        return $this->backParser()->normalizeSchoolYear($value);
    }

    /**
     * @param  array{text: string, provider: string, raw: array<string, mixed>|null}  $ocr
     * @param  array{full_name: string, student_id: string}  $expected
     * @return array{ok: bool, extracted_name: ?string, extracted_student_id: ?string, errors: array<string, string>}
     */
    public function matchAgainstExpected(array $ocr, array $expected): array
    {
        return $this->identityMatcher()->matchAgainstExpected($ocr, $expected);
    }

    /**
     * @return array{text: string, provider: string, raw: array<string, mixed>|null}
     */
    private function viaOcrSpace(UploadedFile $file, string $apiKey): array
    {
        $response = Http::asMultipart()
            ->timeout((int) config('services.identity.ocr_space_timeout', 60))
            ->attach('file', fopen($file->getRealPath(), 'r'), $file->getClientOriginalName())
            ->post('https://api.ocr.space/parse/image', [
                'apikey' => $apiKey,
                'language' => 'eng',
                'isOverlayRequired' => 'false',
                'OCREngine' => '2',
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('OCR.space request failed.');
        }

        $payload = $response->json() ?? [];
        if (data_get($payload, 'IsErroredOnProcessing')) {
            throw new \RuntimeException((string) data_get($payload, 'ErrorMessage.0', 'OCR.space could not read the ID.'));
        }

        $text = collect(data_get($payload, 'ParsedResults', []))
            ->pluck('ParsedText')
            ->filter()
            ->implode("\n");

        return [
            'text' => $text,
            'provider' => 'ocr_space',
            'raw' => $payload,
            'qr' => $this->emptyQr(),
        ];
    }

    /**
     * @return array{text: string, provider: string, raw: array<string, mixed>|null, qr: array{found: bool, value: ?string, type: ?string, engine: ?string}}
     */
    private function viaLocalOcr(UploadedFile $file): array
    {
        $baseUrl = rtrim((string) config('services.ocr.url'), '/');
        if ($baseUrl === '') {
            throw new \RuntimeException('No OCR provider configured. Set OCR_SERVICE_URL (local :8001) for development.');
        }

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('services.ocr.timeout', 120))
                ->attach('file', fopen($file->getRealPath(), 'r'), $file->getClientOriginalName())
                ->post($baseUrl.'/ocr/image');
        } catch (ConnectionException $exception) {
            throw new \RuntimeException(
                'Local OCR service is unavailable on '.$baseUrl.'. Start backend/ocr-service (uvicorn :8001). Never bind PHP on 8001.',
                0,
                $exception,
            );
        }

        if ($response->failed()) {
            $message = (string) data_get($response->json(), 'error.message', '');
            throw new \RuntimeException(
                $message !== ''
                    ? 'Local OCR failed: '.$message
                    : 'Local OCR service returned an error. Check ocr-service logs on :8001.',
            );
        }

        $payload = $response->json() ?? [];
        // Empty cleaned_text is a successful OCR result (common on sparse ID backs) — not a failure.
        $text = (string) (data_get($payload, 'result.cleaned_text') ?: data_get($payload, 'result.combined_text') ?: '');

        return [
            'text' => $text,
            'provider' => 'local_ocr',
            'raw' => $payload,
            'qr' => $this->qrFromPayload($payload),
        ];
    }

    private function backParser(): IdCardBackParser
    {
        return $this->backParser ??= new IdCardBackParser;
    }

    private function identityMatcher(): IdCardIdentityMatcher
    {
        return $this->identityMatcher ??= new IdCardIdentityMatcher;
    }
}
