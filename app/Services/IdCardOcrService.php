<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class IdCardOcrService
{
    /**
     * Extract printable text from an ID card image via OCR.space or local OCR service.
     *
     * @return array{text: string, provider: string, raw: array<string, mixed>|null}
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
     * @param  array{text: string, provider: string, raw: array<string, mixed>|null}  $ocr
     * @param  array{full_name: string, student_id: string}  $expected
     * @return array{ok: bool, extracted_name: ?string, extracted_student_id: ?string, errors: array<string, string>}
     */
    public function matchAgainstExpected(array $ocr, array $expected): array
    {
        $text = $this->key($ocr['text'] ?? '');
        $name = $this->key($expected['full_name']);
        $studentId = $this->key($expected['student_id']);

        $extractedName = $this->findNeedleInHaystack($name, $text) ? $expected['full_name'] : $this->guessName($ocr['text'] ?? '');
        $extractedStudentId = $this->findNeedleInHaystack($studentId, $text)
            ? $expected['student_id']
            : $this->guessStudentId($ocr['text'] ?? '');

        $errors = [];
        if (! $this->findNeedleInHaystack($name, $text) && ! $this->namesLooselyMatch($name, $this->key((string) $extractedName))) {
            $errors['ocr_name'] = 'OCR name on the School ID does not match the KYC / masterlist record.';
        }
        if (! $this->findNeedleInHaystack($studentId, $text) && $this->key((string) $extractedStudentId) !== $studentId) {
            $errors['ocr_student_id'] = 'OCR student ID on the School ID does not match the KYC / masterlist record.';
        }

        return [
            'ok' => $errors === [],
            'extracted_name' => $extractedName,
            'extracted_student_id' => $extractedStudentId,
            'errors' => $errors,
        ];
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

        return ['text' => $text, 'provider' => 'ocr_space', 'raw' => $payload];
    }

    /**
     * @return array{text: string, provider: string, raw: array<string, mixed>|null}
     */
    private function viaLocalOcr(UploadedFile $file): array
    {
        $baseUrl = rtrim((string) config('services.ocr.url'), '/');
        if ($baseUrl === '') {
            throw new \RuntimeException('No OCR provider configured. Set OCR_SPACE_API_KEY or OCR_SERVICE_URL.');
        }

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('services.ocr.timeout', 120))
                ->attach('file', fopen($file->getRealPath(), 'r'), $file->getClientOriginalName())
                ->post($baseUrl.'/ocr/image');
        } catch (ConnectionException $exception) {
            throw new \RuntimeException('Local OCR service is unavailable. Start ocr-service or configure OCR_SPACE_API_KEY.', 0, $exception);
        }

        if ($response->failed()) {
            throw new \RuntimeException((string) data_get($response->json(), 'error.message', 'Local OCR failed.'));
        }

        $payload = $response->json() ?? [];
        $text = (string) (data_get($payload, 'result.cleaned_text') ?: data_get($payload, 'result.combined_text') ?: '');

        return ['text' => $text, 'provider' => 'local_ocr', 'raw' => $payload];
    }

    private function key(string $value): string
    {
        return Str::of($value)->lower()->replaceMatches('/\s+/', ' ')->trim()->toString();
    }

    private function findNeedleInHaystack(string $needle, string $haystack): bool
    {
        if ($needle === '' || $haystack === '') {
            return false;
        }

        return str_contains($haystack, $needle);
    }

    private function namesLooselyMatch(string $expected, string $candidate): bool
    {
        if ($expected === '' || $candidate === '') {
            return false;
        }

        $expectedParts = array_values(array_filter(explode(' ', $expected)));
        $candidateParts = array_values(array_filter(explode(' ', $candidate)));
        if (count($expectedParts) < 2 || count($candidateParts) < 2) {
            return $expected === $candidate;
        }

        $overlap = count(array_intersect($expectedParts, $candidateParts));

        return $overlap >= max(2, (int) floor(count($expectedParts) * 0.6));
    }

    private function guessName(string $text): ?string
    {
        foreach (preg_split('/\R+/', $text) ?: [] as $line) {
            $line = trim($line);
            if (preg_match('/^[A-Za-z][A-Za-z .\-]{4,}$/', $line) && str_word_count($line) >= 2) {
                return $line;
            }
        }

        return null;
    }

    private function guessStudentId(string $text): ?string
    {
        if (preg_match('/\b(?:\d{4}[-–]?\d{4,}|\d{8,12}|[A-Z]{2,4}[--]?\d{3,})\b/i', $text, $matches)) {
            return $matches[0];
        }

        return null;
    }
}
