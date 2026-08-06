<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class IdCardOcrService
{
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
        $raw = trim($text);
        if ($raw === '') {
            return [
                'school_year' => null,
                'emergency_contact_name' => null,
                'emergency_contact_relationship' => null,
                'emergency_contact_phone' => null,
            ];
        }

        $schoolYear = null;
        if (preg_match(
            '/\b(?:S\.?\s*Y\.?|SY|School\s*Year|Academic\s*Year|A\.?\s*Y\.?)\s*[:\-]?\s*((?:20\d{2})\s*[-–\/]\s*(?:20)?\d{2,4})/i',
            $raw,
            $syMatch,
        )) {
            $schoolYear = preg_replace('/\s+/', '', str_replace(['–', '/'], '-', $syMatch[1]));
        } elseif (preg_match('/\b(20\d{2}\s*[-–\/]\s*20\d{2})\b/', $raw, $ayMatch)) {
            $schoolYear = preg_replace('/\s+/', '', str_replace(['–', '/'], '-', $ayMatch[1]));
        }

        $relationship = null;
        if (preg_match(
            '/\b(?:Relationship|Rel\.?)\s*[:\-]?\s*(Mother|Father|Guardian|Parent|Spouse|Sibling|Brother|Sister|Aunt|Uncle|Grandmother|Grandfather|Relative|Other)\b/i',
            $raw,
            $relMatch,
        )) {
            $relationship = ucfirst(strtolower($relMatch[1]));
        } elseif (preg_match(
            '/\b(Mother|Father|Guardian|Parent|Spouse)\b/i',
            $raw,
            $relLoose,
        )) {
            $relationship = ucfirst(strtolower($relLoose[1]));
        }

        $phone = null;
        if (preg_match(
            '/(?:\+?63|0)?[\s\-.]?(?:9\d{2}|2\d{2}|\d{3})[\s\-.]?\d{3}[\s\-.]?\d{4}\b/',
            $raw,
            $phoneMatch,
        )) {
            $phone = preg_replace('/[^\d+]/', '', $phoneMatch[0]);
        }

        $contactName = null;
        if (preg_match(
            '/(?:Emergency\s*(?:Contact|Person)|In\s*case\s*of\s*emergency|Contact\s*Person|Guardian)\s*[:\-]?\s*([A-Za-z][A-Za-z .\-]{2,60})/i',
            $raw,
            $nameMatch,
        )) {
            $candidate = trim(preg_replace('/\s+/', ' ', $nameMatch[1]));
            $candidate = preg_replace('/\b(?:Mother|Father|Guardian|Parent|Spouse|Relationship|Rel|Contact|Phone|Mobile|Tel)\b.*$/i', '', $candidate);
            $candidate = trim((string) $candidate, " \t:-");
            if ($candidate !== '' && str_word_count($candidate) >= 1) {
                $contactName = $candidate;
            }
        }

        return [
            'school_year' => $this->normalizeSchoolYear($schoolYear),
            'emergency_contact_name' => $contactName,
            'emergency_contact_relationship' => $relationship,
            'emergency_contact_phone' => $phone,
        ];
    }

    /**
     * Normalize academic / school year strings for soft comparison
     * (e.g. "SY 2026-2027", "2026–27", "2026/2027" → "2026-2027").
     */
    public function normalizeSchoolYear(?string $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $normalized = preg_replace('/\s+/', '', str_replace(['–', '—', '/'], '-', $raw));
        $normalized = (string) preg_replace(
            '/^(?:S\.?Y\.?|SY|A\.?Y\.?|ACADEMICYEAR|SCHOOLYEAR)[:\-]*/i',
            '',
            (string) $normalized,
        );

        if (preg_match('/^(20\d{2})-(\d{2}|\d{4})$/', $normalized, $match)) {
            $start = $match[1];
            $end = $match[2];
            if (strlen($end) === 2) {
                $end = substr($start, 0, 2).$end;
            }

            return "{$start}-{$end}";
        }

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @param  array{text: string, provider: string, raw: array<string, mixed>|null}  $ocr
     * @param  array{full_name: string, student_id: string}  $expected
     * @return array{ok: bool, extracted_name: ?string, extracted_student_id: ?string, errors: array<string, string>}
     */
    public function matchAgainstExpected(array $ocr, array $expected): array
    {
        $rawText = (string) ($ocr['text'] ?? '');
        $text = $this->key($rawText);
        $name = $this->key($expected['full_name']);

        $idHardMatch = $this->studentIdMatches($expected['student_id'], $rawText);
        $extractedName = $this->findNeedleInHaystack($name, $text) ? $expected['full_name'] : $this->guessName($rawText);
        $extractedStudentId = $idHardMatch
            ? $expected['student_id']
            : $this->guessStudentId($rawText);

        $nameExact = $this->findNeedleInHaystack($name, $text);
        $nameLoose = $this->namesLooselyMatch($name, $this->key((string) $extractedName));
        // Soften name when student ID already hard-matches: one clear name token is enough
        // (OCR often garbles the full line but still prints RAFAEL or BALACUIT).
        $nameFuzzy = $idHardMatch && (
            $this->namesFuzzyMatch($name, $text)
            || $this->namesFuzzyMatch($name, $this->key((string) $extractedName))
            || $this->namesTokenPartialMatch($name, $text)
        );

        if ($nameFuzzy && ! $nameExact) {
            $extractedName = $expected['full_name'];
        }

        // Map match failures to id_frame so the UI does not look like a back-side OCR failure.
        // Program is not part of School ID OCR matching (KYC/masterlist handles program separately).
        $errors = [];
        if (! $nameExact && ! $nameLoose && ! $nameFuzzy) {
            $snippet = $this->ocrSawSnippet($rawText);
            if ($idHardMatch) {
                // ID is fine — name OCR is garbage / unreadable. Ask for a sharper front retake.
                $errors['id_frame'] = 'Front of School ID: student ID matches, but the name is unreadable.'
                    .' Retake the front in brighter light with less glare so the full name is sharp.'
                    .($snippet !== '' ? ' OCR saw: '.$snippet : '');
            } else {
                $errors['id_frame'] = 'Front of School ID: OCR name does not match the KYC / masterlist record.'
                    .($snippet !== '' ? ' OCR saw: '.$snippet : '');
            }
        }
        if (! $idHardMatch) {
            $ocrRead = $extractedStudentId !== null && $extractedStudentId !== ''
                ? (string) $extractedStudentId
                : 'nothing clear';
            $idDetail = ' Expected student ID '.$expected['student_id'].'; OCR read: '.$ocrRead.'.';
            $nameMatched = $nameExact || $nameLoose || $nameFuzzy;
            if ($nameMatched) {
                // Name is fine — avoid implying the masterlist name is wrong.
                $errors['id_frame'] = 'Front of School ID: name matches KYC / masterlist, but OCR student ID does not match.'
                    .$idDetail
                    .' Retake the front in brighter light so the student number is sharp.';
            } else {
                $errors['id_frame'] = ($errors['id_frame'] ?? '')
                    ? $errors['id_frame'].' Student ID on the front also does not match.'.$idDetail
                    : 'Front of School ID: OCR student ID does not match the KYC / masterlist record.'.$idDetail;
            }
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

    private function key(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->replaceMatches('/[^\p{L}\p{N}\s]+/u', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();
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

    /**
     * Per-token fuzzy match with small Levenshtein distance.
     * Only used when student ID already hard-matches (caller gate).
     * Accepts a single clear name token (e.g. surname) when the ID already matches.
     */
    private function namesFuzzyMatch(string $expected, string $haystack): bool
    {
        if ($expected === '' || $haystack === '') {
            return false;
        }

        $expectedParts = array_values(array_filter(explode(' ', $expected), fn (string $part) => strlen($part) >= 2));
        $hayTokens = array_values(array_filter(explode(' ', $haystack), fn (string $part) => strlen($part) >= 2));
        if ($expectedParts === [] || $hayTokens === []) {
            return false;
        }

        $matched = 0;
        $strongMatched = 0;
        foreach ($expectedParts as $part) {
            foreach ($hayTokens as $token) {
                if ($this->tokensFuzzyEqual($part, $token)) {
                    $matched++;
                    if (strlen($part) >= 4) {
                        $strongMatched++;
                    }
                    break;
                }
            }
        }

        // ID already matched: one strong token (surname/given ≥4 chars) is enough.
        if ($strongMatched >= 1) {
            return true;
        }

        return $matched >= max(2, (int) floor(count($expectedParts) * 0.6));
    }

    /**
     * Contiguous substring / token-in-haystack check for OCR that keeps letters but
     * destroys spacing (e.g. "...BALACUIT..." inside junk). ID-hard-match gate only.
     */
    private function namesTokenPartialMatch(string $expected, string $haystack): bool
    {
        if ($expected === '' || $haystack === '') {
            return false;
        }

        $hayCollapsed = preg_replace('/\s+/', '', $haystack) ?? '';
        $expectedParts = array_values(array_filter(
            explode(' ', $expected),
            fn (string $part) => strlen($part) >= 4,
        ));

        foreach ($expectedParts as $part) {
            if (str_contains($haystack, $part) || str_contains($hayCollapsed, $part)) {
                return true;
            }
        }

        return false;
    }

    private function tokensFuzzyEqual(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }

        $maxLen = max(strlen($a), strlen($b));
        if ($maxLen < 3) {
            return false;
        }

        // Short tokens: 1 edit; longer surnames: up to 2.
        $allowed = $maxLen <= 5 ? 1 : 2;

        return levenshtein($a, $b) <= $allowed;
    }

    private function ocrSawSnippet(string $rawText): string
    {
        $collapsed = trim(preg_replace('/\s+/u', ' ', $rawText) ?? '');
        if ($collapsed === '') {
            return '';
        }

        return Str::limit($collapsed, 120, '…');
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
        // Prefer labeled student-ID lines ("ID Number: 20231909", "Student No. 2023-1909").
        if (preg_match_all(
            '/(?:ID\s*[,.]?\s*Number|Student\s*(?:No\.?|Number|ID)|ID\s*No\.?)\s*[:\-.]?\s*([A-Z0-9][A-Z0-9\s\-–.]{3,24})/i',
            $text,
            $labeled,
        ) > 0) {
            $best = null;
            $bestLen = 0;
            foreach ($labeled[1] as $candidate) {
                $digits = $this->digitsOnly($candidate);
                $len = strlen($digits);
                if ($len >= 6 && $len <= 12 && $len > $bestLen) {
                    $best = $digits;
                    $bestLen = $len;
                }
            }
            if ($best !== null) {
                return $best;
            }
        }

        // Prefer collapsed digit runs (handles "2023 1909" / "2023-1909").
        if (preg_match_all('/(?:\d[\d\s\-–.]*){6,}\d/', $text, $runs) > 0) {
            $best = null;
            $bestLen = 0;
            foreach ($runs[0] as $run) {
                $digits = $this->digitsOnly($run);
                $len = strlen($digits);
                if ($len >= 6 && $len <= 12 && $len > $bestLen) {
                    $best = $digits;
                    $bestLen = $len;
                }
            }
            if ($best !== null) {
                return $best;
            }
        }

        if (preg_match('/\b(?:\d{4}[-–]?\d{4,}|\d{8,12}|[A-Z]{2,4}[-–]?\d{3,})\b/i', $text, $matches)) {
            return $matches[0];
        }

        return null;
    }

    /**
     * Match student IDs even when OCR inserts spaces/dashes or confuses digit lookalikes
     * inside a number token (e.g. "2023 1909", "202S1909"). Does not compare program.
     */
    private function studentIdMatches(string $expectedId, string $rawText): bool
    {
        $expectedKey = $this->key($expectedId);
        $textKey = $this->key($rawText);
        if ($this->findNeedleInHaystack($expectedKey, $textKey)) {
            return true;
        }

        $expectedDigits = $this->digitsOnly($expectedId);
        if ($expectedDigits === '' || strlen($expectedDigits) < 4) {
            return false;
        }

        $haystackDigits = $this->ocrDigitStream($rawText);
        if ($haystackDigits !== '' && str_contains($haystackDigits, $expectedDigits)) {
            return true;
        }

        // Allow one OCR substitution inside an equal-length digit window (3↔S→5, etc.).
        $len = strlen($expectedDigits);
        $hayLen = strlen($haystackDigits);
        if ($hayLen < $len) {
            return false;
        }

        $maxDistance = $len >= 8 ? 1 : 0;
        if ($maxDistance === 0) {
            return false;
        }

        for ($offset = 0; $offset <= $hayLen - $len; $offset++) {
            $window = substr($haystackDigits, $offset, $len);
            if (levenshtein($window, $expectedDigits) <= $maxDistance) {
                return true;
            }
        }

        return false;
    }

    private function digitsOnly(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    /**
     * Build a digit-only stream from OCR text. Confusable letters are mapped only inside
     * tokens that already contain digits so names/programs (BSIT) do not become fake IDs.
     */
    private function ocrDigitStream(string $text): string
    {
        $confusable = [
            'O' => '0', 'o' => '0', 'Q' => '0', 'q' => '0', 'D' => '0',
            'I' => '1', 'i' => '1', 'l' => '1', '|' => '1',
            'Z' => '2', 'z' => '2',
            'S' => '5', 's' => '5',
            'G' => '6', 'g' => '6',
            'B' => '8', 'b' => '8',
        ];

        $normalized = preg_replace_callback(
            '/(?:\d|[OoQqDdIiLlZzSsGgBb|])+(?:[\s\-–.]*(?:\d|[OoQqDdIiLlZzSsGgBb|])+)+|\d{4,}/',
            static function (array $match) use ($confusable): string {
                $mapped = strtr($match[0], $confusable);

                return preg_replace('/\D+/', '', $mapped) ?? '';
            },
            $text,
        );

        return $this->digitsOnly((string) $normalized);
    }
}
