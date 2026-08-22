<?php

namespace App\Services;

use Illuminate\Support\Str;

class AcademicTermAnalyzer
{
    public function termSortKey(?string $academicTerm): int
    {
        if ($academicTerm === null || trim($academicTerm) === '') {
            return 0;
        }
        $normalized = trim(preg_replace('/\s+/', ' ', $academicTerm) ?? '');
        if (! preg_match('/(20\d{2})\s*[-–\/]\s*(20\d{2}|\d{2})\s+(1st|2nd|3rd|Summer|Midyear)/iu', $normalized, $m)) {
            return 0;
        }
        $start = (int) $m[1];
        $endRaw = $m[2];
        $end = strlen($endRaw) === 2 ? (int) (substr((string) $start, 0, 2).$endRaw) : (int) $endRaw;
        $season = match (Str::lower($m[3])) {
            '1st' => 1,
            '2nd' => 2,
            '3rd' => 3,
            'summer', 'midyear' => 4,
            default => 0,
        };

        return ($start * 10000) + ($end * 10) + $season;
    }

    /**
     * @param  list<array<string, mixed>>  $terms
     * @return list<string>
     */
    public function pendingTermKeys(array $terms, ?string $gradeSlipTerm = null): array
    {
        $byKey = [];
        foreach ($terms as $term) {
            if (! is_array($term)) {
                continue;
            }
            $label = trim((string) ($term['academic_term'] ?? ''));
            if ($label === '') {
                continue;
            }
            $sort = $this->termSortKey($label);
            if ($sort <= 0) {
                continue;
            }
            $canon = preg_replace('/\s+/', ' ', $label) ?? $label;
            if (! isset($byKey[$canon]) || $sort > $byKey[$canon]) {
                $byKey[$canon] = $sort;
            }
        }
        if ($byKey === []) {
            return [];
        }
        arsort($byKey, SORT_NUMERIC);

        $gsLabel = is_string($gradeSlipTerm) ? trim(preg_replace('/\s+/', ' ', $gradeSlipTerm) ?? '') : '';
        if ($gsLabel !== '') {
            $gsSort = $this->termSortKey($gsLabel);
            $pending = [];
            foreach ($byKey as $canon => $sort) {
                if (strcasecmp($canon, $gsLabel) === 0 || ($gsSort > 0 && $sort > $gsSort)) {
                    $pending[] = $canon;
                }
            }

            if ($pending === [] || ! in_array($gsLabel, $pending, true)) {
                $matched = false;
                foreach ($pending as $p) {
                    if (strcasecmp($p, $gsLabel) === 0) {
                        $matched = true;
                        break;
                    }
                }
                if (! $matched) {
                    array_unshift($pending, $gsLabel);
                }
            }

            return array_values(array_unique($pending));
        }

        $newestFirst = array_keys($byKey);
        $pending = [];
        if (isset($newestFirst[0])) {
            $pending[] = $newestFirst[0];
        }
        if (isset($newestFirst[1]) && $this->termHasBlankGrades($terms, $newestFirst[1])) {
            $pending[] = $newestFirst[1];
        }

        return $pending;
    }

    /**
     * @param  list<array<string, mixed>>  $terms
     */
    public function termHasBlankGrades(array $terms, string $academicTermCanon): bool
    {
        foreach ($terms as $term) {
            if (! is_array($term)) {
                continue;
            }
            $label = trim(preg_replace('/\s+/', ' ', (string) ($term['academic_term'] ?? '')) ?? '');
            if ($label === '' || strcasecmp($label, $academicTermCanon) !== 0) {
                continue;
            }
            $courses = is_array($term['courses'] ?? null) ? $term['courses'] : [];
            foreach ($courses as $course) {
                if (! is_array($course)) {
                    continue;
                }
                $grade = isset($course['grade']) ? trim((string) $course['grade']) : '';
                if ($grade === '' || preg_match('/^(?:—|-|–|inc|ng|n\/a)$/i', $grade) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $gradeSlipParsed
     * @param  list<array<string, mixed>>|null  $courseHistoryTerms
     */
    public function resolveGradeSlipTerm(array $gradeSlipParsed, ?array $courseHistoryTerms = null): ?string
    {
        $terms = $gradeSlipParsed['terms'] ?? null;
        if (is_array($terms) && $terms !== []) {
            $best = null;
            $bestSort = -1;
            foreach ($terms as $term) {
                if (! is_array($term)) {
                    continue;
                }
                $label = trim((string) ($term['academic_term'] ?? ''));
                if ($label === '') {
                    continue;
                }
                $sort = $this->termSortKey($label);
                if ($sort > $bestSort) {
                    $bestSort = $sort;
                    $best = preg_replace('/\s+/', ' ', $label) ?? $label;
                }
            }
            if (is_string($best) && $best !== '') {
                return $best;
            }
        }

        $year = isset($gradeSlipParsed['academic_year']) ? trim((string) $gradeSlipParsed['academic_year']) : '';
        $sem = isset($gradeSlipParsed['semester_label']) ? trim((string) $gradeSlipParsed['semester_label']) : '';
        if ($sem !== '' && $this->termSortKey($sem) > 0) {
            return preg_replace('/\s+/', ' ', $sem) ?? $sem;
        }
        if ($year !== '' && $sem !== '') {
            return trim(preg_replace('/\s+/', ' ', $year.' '.$sem) ?? ($year.' '.$sem));
        }

        $gsCourses = is_array($gradeSlipParsed['courses'] ?? null) ? $gradeSlipParsed['courses'] : [];
        if ($gsCourses !== [] && is_array($courseHistoryTerms) && $courseHistoryTerms !== []) {
            return $this->inferGradeSlipTermFromCourseOverlap($gsCourses, $courseHistoryTerms);
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $gsCourses
     * @param  list<array<string, mixed>>  $chTerms
     */
    public function inferGradeSlipTermFromCourseOverlap(array $gsCourses, array $chTerms): ?string
    {
        $gsCodes = [];
        foreach ($gsCourses as $course) {
            if (! is_array($course)) {
                continue;
            }
            $code = Str::upper(trim((string) ($course['code'] ?? '')));
            if ($code !== '') {
                $gsCodes[$code] = true;
            }
        }
        if ($gsCodes === []) {
            return null;
        }
        $gsCount = count($gsCodes);

        $bestLabel = null;
        $bestScore = 0;
        $bestSort = -1;
        foreach ($chTerms as $term) {
            if (! is_array($term)) {
                continue;
            }
            $label = trim(preg_replace('/\s+/', ' ', (string) ($term['academic_term'] ?? '')) ?? '');
            if ($label === '' || $this->termSortKey($label) <= 0) {
                continue;
            }
            $termCourses = is_array($term['courses'] ?? null) ? $term['courses'] : [];
            $hits = 0;
            foreach ($termCourses as $course) {
                if (! is_array($course)) {
                    continue;
                }
                $code = Str::upper(trim((string) ($course['code'] ?? '')));
                if ($code !== '' && isset($gsCodes[$code])) {
                    $hits++;
                }
            }
            if ($hits === 0) {
                continue;
            }
            $minHits = $gsCount >= 3 ? 2 : 1;
            if ($hits < $minHits) {
                continue;
            }
            $sort = $this->termSortKey($label);
            if ($hits > $bestScore || ($hits === $bestScore && $sort > $bestSort)) {
                $bestScore = $hits;
                $bestSort = $sort;
                $bestLabel = $label;
            }
        }

        return $bestLabel;
    }

    /**
     * @param  array<string, mixed>  $gradeSlipParsed
     */
    public function gradeSlipLooksLikeEmptyEnrollment(array $gradeSlipParsed): bool
    {
        $grades = $gradeSlipParsed['grades'] ?? null;
        $numeric = is_array($grades) ? count(array_filter($grades, 'is_numeric')) : 0;
        $blank = (int) ($gradeSlipParsed['blank_count'] ?? 0);
        $pending = (int) ($gradeSlipParsed['pending_count'] ?? 0);
        $failed = (int) ($gradeSlipParsed['failed_count'] ?? 0);
        $dropped = (int) ($gradeSlipParsed['dropped_count'] ?? 0);

        return $numeric === 0 && $failed === 0 && $dropped === 0 && ($blank + $pending) > 0;
    }

    /**
     * @param  list<array<string, mixed>>|null  $chTerms
     * @param  list<array<string, mixed>>|null  $chCourses
     * @param  list<array<string, mixed>>|null  $gsCourses
     * @return list<array{code: string, ch_grade: mixed, gs_grade: mixed}>
     */
    public function crossCheckChBlanksAgainstGradeSlip(
        ?array $chTerms,
        ?array $chCourses,
        ?array $gsCourses,
        ?string $gradeSlipTerm,
    ): array {
        $gsLabel = is_string($gradeSlipTerm) ? trim(preg_replace('/\s+/', ' ', $gradeSlipTerm) ?? '') : '';
        if ($gsLabel === '' || ! is_array($gsCourses) || $gsCourses === []) {
            return [];
        }

        $gsByCode = [];
        foreach ($gsCourses as $course) {
            if (! is_array($course)) {
                continue;
            }
            $code = Str::upper(trim((string) ($course['code'] ?? '')));
            if ($code === '') {
                continue;
            }
            $grade = isset($course['grade']) ? trim((string) $course['grade']) : '';
            if ($grade === '' || preg_match('/^(?:—|-|–|inc|ng|n\/a)$/i', $grade) === 1) {
                continue;
            }
            if (! is_numeric($grade) && ! preg_match('/^[1-5](?:\.\d{1,2})?$/', $grade)) {
                continue;
            }
            $gsByCode[$code] = $course['grade'];
        }
        if ($gsByCode === []) {
            return [];
        }

        $chRows = [];
        if (is_array($chTerms)) {
            foreach ($chTerms as $term) {
                if (! is_array($term)) {
                    continue;
                }
                $label = trim(preg_replace('/\s+/', ' ', (string) ($term['academic_term'] ?? '')) ?? '');
                if ($label === '' || strcasecmp($label, $gsLabel) !== 0) {
                    continue;
                }
                $termCourses = is_array($term['courses'] ?? null) ? $term['courses'] : [];
                foreach ($termCourses as $course) {
                    if (is_array($course)) {
                        $chRows[] = $course;
                    }
                }
            }
        }
        if ($chRows === [] && is_array($chCourses)) {
            foreach ($chCourses as $course) {
                if (! is_array($course)) {
                    continue;
                }
                $term = trim(preg_replace('/\s+/', ' ', (string) ($course['academic_term'] ?? '')) ?? '');
                if ($term !== '' && strcasecmp($term, $gsLabel) !== 0) {
                    continue;
                }
                $chRows[] = $course;
            }
        }

        $mismatches = [];
        foreach ($chRows as $course) {
            $code = Str::upper(trim((string) ($course['code'] ?? '')));
            if ($code === '' || ! isset($gsByCode[$code])) {
                continue;
            }
            $chGrade = isset($course['grade']) ? trim((string) $course['grade']) : '';
            $isBlank = $chGrade === '' || preg_match('/^(?:—|-|–|inc|ng|n\/a)$/i', $chGrade) === 1;
            if (! $isBlank) {
                continue;
            }
            $mismatches[] = [
                'code' => $code,
                'ch_grade' => $course['grade'] ?? null,
                'gs_grade' => $gsByCode[$code],
            ];
        }

        return $mismatches;
    }

    /**
     * @param  'course_history'|'grade_slip'|null  $documentType
     * @param  list<string>  $pendingKeys
     * @return 'informational'|'pending'|'dropped'
     */
    public function blankModeForTerm(?string $documentType, string $academicTerm, array $pendingKeys): string
    {
        if ($documentType !== 'course_history') {
            return 'informational';
        }
        if ($pendingKeys === []) {
            return 'pending';
        }
        if ($academicTerm === '') {
            return 'pending';
        }
        $canon = preg_replace('/\s+/', ' ', $academicTerm) ?? $academicTerm;
        foreach ($pendingKeys as $key) {
            if (strcasecmp($canon, $key) === 0) {
                return 'pending';
            }
        }

        return 'dropped';
    }
}
