<?php

namespace App\Services;

use App\Models\Grantee;
use App\Models\KycProfile;
use App\Models\MasterlistRow;
use Illuminate\Support\Str;

class MasterlistTruthService
{
    /**
     * @return array{full_name: string, student_id: string, program: string, year_level: string|null}
     */
    public function forGrantee(Grantee $grantee): array
    {
        $row = MasterlistRow::query()
            ->where('student_id', $grantee->student_id)
            ->where('status', 'valid')
            ->whereHas('import', function ($query) use ($grantee): void {
                $query->where('status', 'imported');
                if ($grantee->batch_id) {
                    $query->where('batch_id', $grantee->batch_id);
                }
            })
            ->latest('id')
            ->first();

        if ($row) {
            return [
                'full_name' => (string) $row->full_name,
                'student_id' => (string) $row->student_id,
                'program' => (string) $row->program,
                'year_level' => $row->year_level !== null ? (string) $row->year_level : null,
            ];
        }

        return [
            'full_name' => (string) $grantee->full_name,
            'student_id' => (string) $grantee->student_id,
            'program' => (string) $grantee->program,
            'year_level' => $grantee->year_level !== null ? (string) $grantee->year_level : null,
        ];
    }

    /**
     * @return array{full_name: string, student_id: string}
     */
    public function expectedIdentity(Grantee $grantee, ?KycProfile $profile = null): array
    {
        $truth = $this->forGrantee($grantee);
        $profile ??= $grantee->kycProfile;

        return [
            'full_name' => (string) ($profile?->full_name ?: $truth['full_name']),
            'student_id' => (string) ($profile?->student_id ?: $truth['student_id']),
        ];
    }

    public function key(mixed $value): string
    {
        return $this->normalizeComparable($value);
    }

    /**
     * Casefold, trim, collapse whitespace; optionally strip punctuation for name/ID matching.
     */
    public function normalizeComparable(mixed $value, bool $stripPunctuation = true): string
    {
        $text = Str::of((string) $value)->lower()->trim();
        if ($stripPunctuation) {
            $text = $text->replaceMatches('/[^\p{L}\p{N}\s]+/u', ' ');
        }

        return $text->replaceMatches('/\s+/', ' ')->trim()->toString();
    }

    /**
     * Case-insensitive identity match (names / free text). Punctuation and extra spaces ignored.
     */
    public function valuesMatch(mixed $submitted, mixed $expected): bool
    {
        $left = $this->normalizeComparable($submitted);
        $right = $this->normalizeComparable($expected);

        return $left !== '' && $left === $right;
    }

    /**
     * Split a masterlist-style full name into first / middle / last.
     * Supports "First Middle Last" and Philippine "Last, First Middle".
     *
     * @return array{first: string, middle: string, last: string, tokens: list<string>}
     */
    public function parseNameParts(mixed $fullName): array
    {
        $raw = trim((string) $fullName);
        if ($raw === '') {
            return ['first' => '', 'middle' => '', 'last' => '', 'tokens' => []];
        }

        if (str_contains($raw, ',')) {
            [$lastRaw, $restRaw] = array_pad(explode(',', $raw, 2), 2, '');
            $last = $this->normalizeComparable($lastRaw);
            $rest = $this->normalizeComparable($restRaw);
            $restTokens = $rest === '' ? [] : (preg_split('/\s+/u', $rest) ?: []);
            $first = $restTokens[0] ?? '';
            $middle = count($restTokens) > 1
                ? implode(' ', array_slice($restTokens, 1))
                : '';
            $lastTokens = $last === '' ? [] : (preg_split('/\s+/u', $last) ?: []);
            $tokens = array_values(array_filter([...$lastTokens, ...$restTokens], fn (string $t) => $t !== ''));

            return [
                'first' => $first,
                'middle' => $middle,
                'last' => $last,
                'tokens' => $tokens,
            ];
        }

        $normalized = $this->normalizeComparable($raw);
        $tokens = $normalized === '' ? [] : (preg_split('/\s+/u', $normalized) ?: []);
        $count = count($tokens);

        if ($count === 0) {
            return ['first' => '', 'middle' => '', 'last' => '', 'tokens' => []];
        }
        if ($count === 1) {
            return ['first' => $tokens[0], 'middle' => '', 'last' => $tokens[0], 'tokens' => $tokens];
        }
        if ($count === 2) {
            return ['first' => $tokens[0], 'middle' => '', 'last' => $tokens[1], 'tokens' => $tokens];
        }

        return [
            'first' => $tokens[0],
            'middle' => implode(' ', array_slice($tokens, 1, -1)),
            'last' => $tokens[$count - 1],
            'tokens' => $tokens,
        ];
    }

    /**
     * Match submitted name parts against masterlist full_name (case-insensitive).
     * First + last required; middle optional. When middle is provided and the
     * masterlist has a middle, those must match. Omitting middle still matches
     * a masterlist name that includes a middle (e.g. Brandon Nagangga ≈ Brandon X Nagangga).
     */
    public function namesMatch(mixed $firstName, mixed $middleName, mixed $lastName, mixed $expectedFullName): bool
    {
        $first = $this->normalizeComparable($firstName);
        $middle = $this->normalizeComparable($middleName ?? '');
        $last = $this->normalizeComparable($lastName);
        $expectedRaw = trim((string) $expectedFullName);
        $expected = $this->normalizeComparable($expectedFullName);

        if ($first === '' || $last === '' || $expected === '') {
            return false;
        }

        $composed = $middle !== '' ? "{$first} {$middle} {$last}" : "{$first} {$last}";
        if ($composed === $expected) {
            return true;
        }

        $commaForm = $middle !== '' ? "{$last}, {$first} {$middle}" : "{$last}, {$first}";
        if ($this->normalizeComparable($commaForm) === $expected) {
            return true;
        }

        $parsed = $this->parseNameParts($expectedFullName);
        if ($parsed['first'] === '' || $parsed['last'] === '') {
            return false;
        }

        if ($this->namePartEquals($first, $parsed['first']) && $this->namePartEquals($last, $parsed['last'])) {
            return $this->middleAllows($middle, $parsed['middle']);
        }

        // Multi-word surname / flexible token order (First…Last or Last, First…).
        $firstTokens = $this->tokens($first);
        $lastTokens = $this->tokens($last);
        $haystack = $parsed['tokens'];
        if ($firstTokens === [] || $lastTokens === [] || $haystack === []) {
            return false;
        }

        $hasComma = str_contains($expectedRaw, ',');
        if ($hasComma) {
            if (! $this->leadingTokensMatch($lastTokens, $haystack)) {
                return false;
            }
            $rest = array_slice($haystack, count($lastTokens));
            if (! $this->leadingTokensMatch($firstTokens, $rest)) {
                return false;
            }
            $masterMiddleTokens = array_slice($rest, count($firstTokens));
        } else {
            if (! $this->leadingTokensMatch($firstTokens, $haystack)) {
                return false;
            }
            if (! $this->trailingTokensMatch($lastTokens, $haystack)) {
                return false;
            }
            $middleLength = count($haystack) - count($firstTokens) - count($lastTokens);
            $masterMiddleTokens = $middleLength > 0
                ? array_slice($haystack, count($firstTokens), $middleLength)
                : [];
        }

        $masterMiddle = implode(' ', $masterMiddleTokens);

        return $this->middleAllows($middle, $masterMiddle);
    }

    private function namePartEquals(string $left, string $right): bool
    {
        return $left !== '' && $left === $right;
    }

    /**
     * Compare submitted and masterlist middle names.
     *
     * Either side being blank is permissive (middle is optional). Beyond exact
     * equality, an initial on one side matches a full name on the other:
     * masterlists routinely store "Brandon P. Nagangga" while the student types
     * their full middle name "Pagara". Punctuation is already stripped upstream,
     * so "P." arrives here as "p".
     */
    private function middleAllows(string $submittedMiddle, string $masterMiddle): bool
    {
        if ($submittedMiddle === '' || $masterMiddle === '') {
            return true;
        }

        if ($this->namePartEquals($submittedMiddle, $masterMiddle)) {
            return true;
        }

        return $this->middleInitialsAlign($submittedMiddle, $masterMiddle);
    }

    /**
     * True when one middle name is the initial form of the other, comparing
     * token by token so compound middles ("dela cruz" vs "d c") still align.
     */
    private function middleInitialsAlign(string $submittedMiddle, string $masterMiddle): bool
    {
        $submitted = $this->tokens($submittedMiddle);
        $master = $this->tokens($masterMiddle);

        if ($submitted === [] || $master === [] || count($submitted) !== count($master)) {
            return false;
        }

        foreach ($submitted as $index => $submittedToken) {
            $masterToken = $master[$index];

            if ($submittedToken === $masterToken) {
                continue;
            }

            $submittedIsInitial = mb_strlen($submittedToken) === 1;
            $masterIsInitial = mb_strlen($masterToken) === 1;

            // Exactly one side must be an initial, and it must head the other token.
            if ($submittedIsInitial === $masterIsInitial) {
                return false;
            }

            $initial = $submittedIsInitial ? $submittedToken : $masterToken;
            $full = $submittedIsInitial ? $masterToken : $submittedToken;

            if (! str_starts_with($full, $initial)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function tokens(string $normalized): array
    {
        if ($normalized === '') {
            return [];
        }

        return array_values(array_filter(preg_split('/\s+/u', $normalized) ?: [], fn (string $t) => $t !== ''));
    }

    /**
     * @param  list<string>  $needle
     * @param  list<string>  $haystack
     */
    private function leadingTokensMatch(array $needle, array $haystack): bool
    {
        $n = count($needle);
        if ($n === 0 || count($haystack) < $n) {
            return false;
        }

        return array_slice($haystack, 0, $n) === array_values($needle);
    }

    /**
     * @param  list<string>  $needle
     * @param  list<string>  $haystack
     */
    private function trailingTokensMatch(array $needle, array $haystack): bool
    {
        $n = count($needle);
        if ($n === 0 || count($haystack) < $n) {
            return false;
        }

        return array_slice($haystack, -$n) === array_values($needle);
    }

    /**
     * Student ID match: same normalization, plus alnum-only fallback (STU-1 == stu1).
     */
    public function studentIdsMatch(mixed $submitted, mixed $expected): bool
    {
        if ($this->valuesMatch($submitted, $expected)) {
            return true;
        }

        $left = preg_replace('/[^a-z0-9]+/', '', $this->normalizeComparable($submitted)) ?? '';
        $right = preg_replace('/[^a-z0-9]+/', '', $this->normalizeComparable($expected)) ?? '';

        return $left !== '' && $left === $right;
    }

    /**
     * Program may be submitted as academic_programs code or name; masterlist may store either.
     *
     * @param  list<array{code?: string, name?: string}>  $programs
     */
    public function programsMatch(mixed $submitted, mixed $expected, array $programs = []): bool
    {
        if ($this->valuesMatch($submitted, $expected)) {
            return true;
        }

        $submittedKey = $this->normalizeComparable($submitted);
        $expectedKey = $this->normalizeComparable($expected);
        if ($submittedKey === '' || $expectedKey === '') {
            return false;
        }

        foreach ($programs as $program) {
            $code = $this->normalizeComparable($program['code'] ?? '');
            $name = $this->normalizeComparable($program['name'] ?? '');
            $aliases = array_values(array_filter([$code, $name]));
            $submittedHits = in_array($submittedKey, $aliases, true);
            $expectedHits = in_array($expectedKey, $aliases, true);
            if ($submittedHits && $expectedHits) {
                return true;
            }
        }

        return false;
    }
}
