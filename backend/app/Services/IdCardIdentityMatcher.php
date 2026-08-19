<?php

namespace App\Services;

use Illuminate\Support\Str;

class IdCardIdentityMatcher
{
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
        $extractedStudentId = $idHardMatch ? $expected['student_id'] : $this->guessStudentId($rawText);

        $nameExact = $this->findNeedleInHaystack($name, $text);
        $nameLoose = $this->namesLooselyMatch($name, $this->key((string) $extractedName));
        $nameFuzzy = $idHardMatch && (
            $this->namesFuzzyMatch($name, $text)
            || $this->namesFuzzyMatch($name, $this->key((string) $extractedName))
            || $this->namesTokenPartialMatch($name, $text)
        );

        if ($nameFuzzy && ! $nameExact) {
            $extractedName = $expected['full_name'];
        }

        $errors = [];
        if (! $nameExact && ! $nameLoose && ! $nameFuzzy) {
            $snippet = $this->ocrSawSnippet($rawText);
            if ($idHardMatch) {
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

        if ($strongMatched >= 1) {
            return true;
        }

        return $matched >= max(2, (int) floor(count($expectedParts) * 0.6));
    }

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
