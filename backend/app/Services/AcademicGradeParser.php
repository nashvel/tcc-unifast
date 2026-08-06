<?php

namespace App\Services;

use App\Models\AcademicProgram;
use App\Models\PolicySetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AcademicGradeParser
{
    /**
     * CH-only fallback: newest term + optional 2nd-newest (when it has blanks).
     * Prefer Grade Slip academic_term anchor when available.
     */
    public const PENDING_TERM_WINDOW = 2;

    /**
     * Parse Course History / Grade Slip OCR text or structured courses/terms.
     *
     * Document-type blank policy:
     * - grade_slip: blank grades are informational only (blank_count); not retention.
     * - course_history: blanks are Pending on the Grade Slip term and any CH term
     *   strictly newer than that GS term (current enrollment). Older CH blanks count
     *   as Dropped. Without GS: newest term (+ 2nd-newest if it has blanks) = Pending.
     *   Explicit Dropped/DRP remarks always count as dropped.
     *
     * Multi-program (shift): when terms[] carry per-term program, each course is scored
     * against that term's program pass grade (BSED vs BSIT may differ).
     *
     * Failed = numeric grades worse than program pass grade.
     * Retention = overall numeric fails + dropped across full Course History
     * (pending blanks ignored). Not a per-semester cap.
     *
     * Threshold: Settings `max_failed_subjects_per_semester` (overall max; default 3).
     *
     * @param  list<array<string, mixed>>|null  $courses
     * @param  'course_history'|'grade_slip'|null  $documentType
     * @param  list<array<string, mixed>>|null  $terms
     * @param  string|null  $gradeSlipTerm  Academic term from uploaded Grade Slip (e.g. "2025-2026 Summer")
     * @return array<string, mixed>
     */
    public function parse(
        string $text,
        ?string $fallbackProgram = null,
        ?array $courses = null,
        ?string $documentType = null,
        ?array $terms = null,
        ?string $gradeSlipTerm = null,
    ): array {
        $documentType = $this->normalizeDocumentType($documentType);
        $defaultPass = (float) PolicySetting::defaultPassGrade();

        $structuredTerms = $this->normalizeTermsInput($terms, $courses, $text, $fallbackProgram);
        if ($structuredTerms !== []) {
            $summary = $this->summarizeTerms(
                $structuredTerms,
                $documentType,
                $fallbackProgram,
                $defaultPass,
                $gradeSlipTerm,
            );
        } elseif (is_array($courses) && $courses !== []) {
            $programCode = $this->resolveProgramCodeFromLabel(
                $this->extractProgramCode($text) ?: $fallbackProgram
            );
            $passGrade = $this->passGradeForProgram($programCode, $defaultPass);
            $summary = $this->summarizeCourses($courses, $passGrade, $documentType, $programCode);
            $summary['terms'] = [];
        } else {
            $fromLinear = $this->coursesFromLinearText($text);
            $programCode = $this->resolveProgramCodeFromLabel(
                $this->extractProgramCode($text) ?: $fallbackProgram
            );
            $passGrade = $this->passGradeForProgram($programCode, $defaultPass);
            if ($fromLinear !== []) {
                $summary = $this->summarizeCourses($fromLinear, $passGrade, $documentType, $programCode);
            } else {
                $grades = $this->extractNumericGrades($text);
                $numericFailed = 0;
                foreach ($grades as $grade) {
                    if ($grade > $passGrade) {
                        $numericFailed++;
                    }
                }
                $dropped = $this->countDroppedRemarksInText($text);
                $summary = [
                    'grades' => $grades,
                    'courses' => [],
                    'failed_count' => $numericFailed,
                    'blank_count' => 0,
                    'pending_count' => 0,
                    'dropped_count' => $dropped,
                    'numeric_failed_count' => $numericFailed,
                    'retention_count' => $numericFailed + $dropped,
                    'terms' => [],
                    'program_code' => $programCode,
                    'pass_grade' => $passGrade,
                    'program_matched' => $this->findProgram($programCode) !== null,
                ];
            }
            if (! isset($summary['terms'])) {
                $summary['terms'] = [];
            }
        }

        $failedCount = (int) $summary['failed_count'];
        $droppedCount = (int) $summary['dropped_count'];
        $pendingCount = (int) ($summary['pending_count'] ?? 0);
        $programCode = $summary['program_code']
            ?? $this->resolveProgramCodeFromLabel($this->extractProgramCode($text) ?: $fallbackProgram);
        $passGrade = (float) ($summary['pass_grade'] ?? $this->passGradeForProgram($programCode, $defaultPass));

        return [
            'program_code' => $programCode,
            'academic_year' => $this->extractAcademicYear($text),
            'semester_label' => $this->extractSemesterLabel($text),
            'year_level' => $this->extractYearLevel($text),
            'semester_gpa' => $this->extractSemesterGpa($text),
            'grades' => $summary['grades'],
            'courses' => $summary['courses'],
            'terms' => $summary['terms'] ?? [],
            'failed_count' => $failedCount,
            'blank_count' => (int) $summary['blank_count'],
            'pending_count' => $pendingCount,
            'dropped_count' => $droppedCount,
            'numeric_failed_count' => (int) ($summary['numeric_failed_count'] ?? $failedCount),
            // Retention = numeric fails + dropped (GS blanks + CH pending ignored).
            'retention_count' => (int) ($summary['retention_count'] ?? ($failedCount + $droppedCount)),
            'pass_grade' => $passGrade,
            'program_matched' => (bool) ($summary['program_matched'] ?? ($this->findProgram($programCode) !== null)),
            'document_type' => $documentType,
            'source' => $documentType,
            'pending_term_window' => self::PENDING_TERM_WINDOW,
            'grade_slip_term' => $gradeSlipTerm !== null && trim($gradeSlipTerm) !== ''
                ? trim(preg_replace('/\s+/', ' ', $gradeSlipTerm) ?? $gradeSlipTerm)
                : null,
            'terms_detected' => is_array($summary['terms'] ?? null) && ($summary['terms'] ?? []) !== [],
        ];
    }

    /**
     * Score each term with its own program pass grade, then aggregate retention.
     * CH blanks: Pending on GS term + newer CH terms; older → Dropped.
     *
     * @param  list<array<string, mixed>>  $terms
     * @param  'course_history'|'grade_slip'|null  $documentType
     * @return array<string, mixed>
     */
    public function summarizeTerms(
        array $terms,
        ?string $documentType = null,
        ?string $fallbackProgram = null,
        ?float $defaultPass = null,
        ?string $gradeSlipTerm = null,
    ): array {
        $documentType = $this->normalizeDocumentType($documentType);
        $defaultPass ??= (float) PolicySetting::defaultPassGrade();
        $allGrades = [];
        $allCourses = [];
        $normalizedTerms = [];
        $blank = 0;
        $pending = 0;
        $dropped = 0;
        $numericFailed = 0;
        $latestProgram = $this->resolveProgramCodeFromLabel($fallbackProgram);
        $anyMatched = false;

        $pendingKeys = $this->pendingTermKeys($terms, $gradeSlipTerm);

        foreach ($terms as $term) {
            if (! is_array($term)) {
                continue;
            }
            $programRaw = isset($term['program_raw']) ? trim((string) $term['program_raw']) : null;
            $programCode = $this->resolveProgramCodeFromLabel(
                $term['program_code'] ?? $programRaw ?? $fallbackProgram
            );
            if ($programCode) {
                $latestProgram = $programCode;
            }
            $program = $this->findProgram($programCode);
            if ($program !== null) {
                $anyMatched = true;
            }
            $passGrade = (float) ($program?->pass_grade ?? $defaultPass);
            $termCourses = is_array($term['courses'] ?? null) ? $term['courses'] : [];
            $academicTerm = isset($term['academic_term']) ? trim((string) $term['academic_term']) : '';
            $blankMode = $this->blankModeForTerm($documentType, $academicTerm, $pendingKeys);
            $termSummary = $this->summarizeCourses($termCourses, $passGrade, $documentType, $programCode, $blankMode);

            foreach ($termSummary['courses'] as $course) {
                $course['academic_term'] = $term['academic_term'] ?? null;
                $course['program_code'] = $programCode;
                $course['program_raw'] = $programRaw ?: ($term['program_raw'] ?? null);
                $course['year_level'] = $term['year_level'] ?? null;
                $course['pass_grade'] = $passGrade;
                $allCourses[] = $course;
            }
            foreach ($termSummary['grades'] as $g) {
                $allGrades[] = $g;
            }
            $blank += (int) $termSummary['blank_count'];
            $pending += (int) $termSummary['pending_count'];
            $dropped += (int) $termSummary['dropped_count'];
            $numericFailed += (int) $termSummary['failed_count'];

            $normalizedTerms[] = [
                'academic_term' => $academicTerm !== '' ? $academicTerm : null,
                'program_raw' => $programRaw ?: null,
                'program_code' => $programCode,
                'year_level' => isset($term['year_level']) ? (string) $term['year_level'] : null,
                'enrollment_status' => isset($term['enrollment_status']) ? (string) $term['enrollment_status'] : null,
                'pass_grade' => $passGrade,
                'program_matched' => $program !== null,
                'failed_count' => (int) $termSummary['failed_count'],
                'blank_count' => (int) $termSummary['blank_count'],
                'pending_count' => (int) $termSummary['pending_count'],
                'dropped_count' => (int) $termSummary['dropped_count'],
                'retention_count' => (int) $termSummary['retention_count'],
                'blank_mode' => $blankMode,
                'in_pending_window' => $blankMode === 'pending',
                'courses' => $termSummary['courses'],
            ];
        }

        // Chronological order for staff OCR (oldest → newest).
        usort($normalizedTerms, function (array $a, array $b): int {
            return $this->termSortKey($a['academic_term'] ?? null) <=> $this->termSortKey($b['academic_term'] ?? null);
        });

        return [
            'grades' => $allGrades,
            'courses' => $allCourses,
            'terms' => $normalizedTerms,
            'failed_count' => $numericFailed,
            'blank_count' => $blank,
            'pending_count' => $pending,
            'dropped_count' => $dropped,
            'numeric_failed_count' => $numericFailed,
            'retention_count' => $numericFailed + $dropped,
            'program_code' => $latestProgram,
            'pass_grade' => $this->passGradeForProgram($latestProgram, $defaultPass),
            'program_matched' => $anyMatched || $this->findProgram($latestProgram) !== null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $courses
     * @param  'course_history'|'grade_slip'|null  $documentType
     * @param  'informational'|'pending'|'dropped'|null  $blankMode  Override; null auto-derives from document type.
     * @return array<string, mixed>
     */
    public function summarizeCourses(
        array $courses,
        float $passGrade,
        ?string $documentType = null,
        ?string $programCode = null,
        ?string $blankMode = null,
    ): array {
        $documentType = $this->normalizeDocumentType($documentType);
        if ($blankMode === null) {
            // Flat CH without term metadata: treat as current-term → pending.
            $blankMode = $documentType === 'course_history' ? 'pending' : 'informational';
        }
        $grades = [];
        $normalized = [];
        $blank = 0;
        $pending = 0;
        $dropped = 0;
        $numericFailed = 0;

        foreach ($courses as $course) {
            if (! is_array($course)) {
                continue;
            }

            $code = trim((string) ($course['code'] ?? ''));
            $description = trim((string) ($course['description'] ?? ''));
            if ($code === '' && $description === '' && ! isset($course['units']) && ! array_key_exists('grade', $course)) {
                continue;
            }

            // Per-course program override (annotated flat rows from multi-term CH).
            $rowProgram = $this->resolveProgramCodeFromLabel($course['program_code'] ?? $course['program_raw'] ?? null)
                ?: $programCode;
            $rowPass = isset($course['pass_grade']) && is_numeric($course['pass_grade'])
                ? (float) $course['pass_grade']
                : $this->passGradeForProgram($rowProgram, $passGrade);

            $rawGrade = $course['grade'] ?? null;
            if (isset($course['re_grade']) && is_string($course['re_grade']) && trim($course['re_grade']) !== '') {
                $rawGrade = $course['re_grade'];
            }
            $gradeText = is_string($rawGrade) || is_numeric($rawGrade)
                ? trim((string) $rawGrade)
                : '';
            $remarks = trim((string) ($course['remarks'] ?? ''));
            $remarksLower = strtolower($remarks);
            $isDropped = $this->looksLikeDropped($gradeText, $remarksLower);
            $isBlankGrade = $gradeText === '' || preg_match('/^(inc|incomplete|ng|no\s*grade|-|—|–)$/iu', $gradeText) === 1;
            $failReason = null;

            if ($isDropped) {
                $dropped++;
                $failReason = 'dropped';
            } elseif ($isBlankGrade && $blankMode === 'dropped') {
                // Course History older term: blank grade cell means dropped.
                $dropped++;
                $failReason = 'dropped';
                if ($remarks === '') {
                    $remarks = 'Dropped';
                }
            } elseif ($isBlankGrade && $blankMode === 'pending') {
                // Course History current / recent term: blank → pending (not retention).
                $pending++;
                $failReason = 'pending';
                if ($remarks === '') {
                    $remarks = 'Pending';
                }
            } elseif ($isBlankGrade) {
                // Grade Slip: informational only — do not increment failed/retention.
                $blank++;
                $failReason = 'blank';
            } elseif (is_numeric($gradeText)) {
                $candidate = (float) $gradeText;
                if ($this->looksLikeGrade($candidate)) {
                    $grades[] = $candidate;
                    if ($candidate > $rowPass) {
                        $numericFailed++;
                        $failReason = 'numeric_fail';
                    }
                }
            }

            $normalized[] = [
                'code' => $code !== '' ? $code : null,
                'description' => $description !== '' ? $description : null,
                'units' => isset($course['units']) && $course['units'] !== '' && $course['units'] !== null
                    ? (string) $course['units']
                    : null,
                'grade' => $gradeText === '' ? null : $gradeText,
                'instructor' => isset($course['instructor']) && $course['instructor'] !== '' && $course['instructor'] !== null
                    ? (string) $course['instructor']
                    : null,
                'remarks' => $remarks !== '' ? $remarks : null,
                'academic_term' => isset($course['academic_term']) ? (string) $course['academic_term'] : null,
                'program_code' => $rowProgram,
                'program_raw' => isset($course['program_raw']) ? (string) $course['program_raw'] : null,
                'year_level' => isset($course['year_level']) ? (string) $course['year_level'] : null,
                'pass_grade' => $rowPass,
                // Pending + GS blanks flagged for OCR review but do not count as fails.
                // Dropped and numeric fails count toward retention.
                'counts_as_fail' => $failReason !== null && ! in_array($failReason, ['blank', 'pending'], true),
                'fail_reason' => $failReason,
            ];
        }

        return [
            'grades' => $grades,
            'courses' => $normalized,
            // failed_count = numeric fails only (staff "Failed" indicator).
            'failed_count' => $numericFailed,
            'blank_count' => $blank,
            'pending_count' => $pending,
            'dropped_count' => $dropped,
            'numeric_failed_count' => $numericFailed,
            // Retention = numeric fails + dropped (GS blanks + CH pending ignored).
            'retention_count' => $numericFailed + $dropped,
            'program_code' => $programCode,
            'pass_grade' => $passGrade,
            'program_matched' => $this->findProgram($programCode) !== null,
        ];
    }

    /**
     * Sort key for academic terms like "2025-2026 2nd", "2025-2026 Summer".
     * Higher = newer. Unknown terms sort to 0.
     */
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
     * Academic terms whose CH blanks count as Pending (not Dropped).
     *
     * With Grade Slip term: that term + any CH term strictly newer.
     * Without GS: newest term always; 2nd-newest only if it has any blank/INC grades.
     *
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
            // GS term not found on CH labels — still treat GS label as pending anchor.
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

        // CH-only fallback: newest always; 2nd-newest when it has blanks (assumed last graded slip).
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
     * Best academic_term label from a Grade Slip parse / OCR summary.
     *
     * When GS has no year/semester (common for table-only OCR), optionally infer
     * the term by matching GS course codes against Course History terms[].
     *
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
        // semester_label may already be a full term ("2025-2026 Summer") from extractSemesterLabel.
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
     * Infer GS academic term by maximizing course-code overlap with a CH term.
     * Used when Grade Slip OCR has courses but no year/semester header.
     *
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
            // Require majority of GS codes (or at least 2 when GS has 3+ courses).
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
     * True when Grade Slip looks like current-enrollment (no graded rows).
     *
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
     * CH blank on GS term while Grade Slip has a numeric grade for the same course code.
     *
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
    private function blankModeForTerm(?string $documentType, string $academicTerm, array $pendingKeys): string
    {
        if ($documentType !== 'course_history') {
            return 'informational';
        }
        if ($pendingKeys === []) {
            // No parseable terms → treat as current (pending).
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

    public function extractProgramCode(string $text): ?string
    {
        if (preg_match('/(.+?)\s*[—\-–]\s*Year\b/iu', $text, $m)) {
            // Prefer the program token immediately before the Year dash (handles "BSED Filipino").
            $before = trim(preg_replace('/\s+/', ' ', $m[1]) ?? '');
            // Strip leading academic-term noise: "2023-2024 1st BSED Filipino"
            if (preg_match('/(20\d{2}\s*[-–\/]\s*(?:20\d{2}|\d{2})\s+(?:1st|2nd|3rd|Summer|Midyear))\s+(.+)$/iu', $before, $tm)) {
                $before = trim($tm[2]);
            }
            $resolved = $this->resolveProgramCodeFromLabel($before);
            if ($resolved) {
                return $resolved;
            }
        }

        if (preg_match('/\bProgram\s*[:#]?\s*([A-Z]{2,12})\b/iu', $text, $m)) {
            return $this->resolveProgramCodeFromLabel($m[1]);
        }

        if (preg_match('/\b(?:20\d{2})\s*[-–\/]\s*(?:20\d{2}|\d{2}).{0,40}?\b([A-Z]{2,8})\b/iu', $text, $m)) {
            $candidate = $this->resolveProgramCodeFromLabel($m[1]);
            if ($candidate && ! in_array(Str::upper($candidate), ['PATH', 'GEC', 'MATH', 'ROTC', 'CWTS', 'NSTP'], true)) {
                return $candidate;
            }
        }

        $known = AcademicProgram::query()->pluck('code');
        foreach ($known as $code) {
            if (preg_match('/\b'.preg_quote((string) $code, '/').'\b/i', $text)) {
                return (string) $code;
            }
        }

        return null;
    }

    /**
     * Map labels like "BSED Filipino" / "BSIT" to academic_programs.code.
     */
    public function resolveProgramCodeFromLabel(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $raw = trim(preg_replace('/\s+/', ' ', $value) ?? '');
        if ($raw === '') {
            return null;
        }

        $compact = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $raw) ?? '');
        if ($compact === '') {
            return null;
        }

        /** @var Collection<int, string> $codes */
        $codes = AcademicProgram::query()->pluck('code');
        // Exact code match (case-insensitive).
        foreach ($codes as $code) {
            if (strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $code) ?? '') === $compact) {
                return (string) $code;
            }
        }
        // Longest-prefix: "BSEDFILIPINO" starts with BSED.
        $sorted = $codes->sortByDesc(fn ($c) => strlen((string) $c))->values();
        foreach ($sorted as $code) {
            $codeCompact = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $code) ?? '');
            if ($codeCompact !== '' && str_starts_with($compact, $codeCompact)) {
                return (string) $code;
            }
        }
        // Match against program full name tokens.
        $programs = AcademicProgram::query()->get(['code', 'name']);
        foreach ($programs as $program) {
            $nameCompact = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $program->name) ?? '');
            if ($nameCompact !== '' && (str_contains($nameCompact, $compact) || str_contains($compact, $nameCompact))) {
                return (string) $program->code;
            }
        }

        // Fallback: first alphanumeric token uppercased (e.g. BSIT).
        if (preg_match('/^([A-Za-z]{2,12})/', $raw, $m)) {
            return strtoupper($m[1]);
        }

        return null;
    }

    /** @return list<float> */
    public function extractNumericGrades(string $text): array
    {
        $grades = [];
        if (preg_match_all('/\b([1-6])\s+([1-5](?:\.\d{1,2})?)\b/', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $grade = (float) $match[2];
                if ($this->looksLikeGrade($grade)) {
                    $grades[] = $grade;
                }
            }
        }
        if ($grades !== []) {
            return $grades;
        }
        $stripped = preg_replace('/(?:Semester\s*GPA|GWA)\s*[:#]?\s*[0-9]+(?:\.[0-9]+)?/iu', '', $text) ?? $text;
        if (preg_match_all('/\b([1-5]\.\d{1,2})\b/', $stripped, $decimalMatches)) {
            foreach ($decimalMatches[1] as $raw) {
                $grade = (float) $raw;
                if ($this->looksLikeGrade($grade)) {
                    $grades[] = $grade;
                }
            }
        }

        return $grades;
    }

    /** @return list<array<string, mixed>> */
    public function coursesFromLinearText(string $text): array
    {
        $courses = [];
        if (preg_match_all('/\b([1-6])\s+([1-5](?:\.\d{1,2})?)\b/', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $courses[] = [
                    'code' => null,
                    'description' => null,
                    'units' => $match[1],
                    'grade' => $match[2],
                    'instructor' => null,
                    'remarks' => null,
                ];
            }
        }
        if (preg_match_all('/\b([1-6])\s+([A-Z][A-Za-zÑñ\'\-]+,\s*[A-Z][A-Za-zÑñ\'\-\s\.]+)/u', $text, $blankMatches, PREG_SET_ORDER)) {
            foreach ($blankMatches as $match) {
                $courses[] = [
                    'code' => null,
                    'description' => null,
                    'units' => $match[1],
                    'grade' => null,
                    'instructor' => trim($match[2]),
                    'remarks' => null,
                ];
            }
        }

        return $courses;
    }

    /**
     * Parse CH term headers from linear OCR text into terms with empty courses
     * (used when Python did not return terms[] but text still has block headers).
     *
     * @return list<array<string, mixed>>
     */
    public function termsFromLinearText(string $text): array
    {
        $terms = [];
        $pattern = '/(20\d{2}\s*[-–\/]\s*(?:20\d{2}|\d{2})\s+(?:1st|2nd|3rd|Summer|Midyear))\s+(.+?)\s*[—\-–]\s*Year\s*(1st|2nd|3rd|4th|5th|First|Second|Third|Fourth|[1-5])(?:\s+(ENROLLED|ACCEPTED|DROPPED))?/iu';
        if (! preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            return [];
        }
        foreach ($matches as $m) {
            $programRaw = trim(preg_replace('/\s+/', ' ', $m[2]) ?? '');
            $terms[] = [
                'academic_term' => trim(preg_replace('/\s+/', ' ', $m[1]) ?? ''),
                'program_raw' => $programRaw !== '' ? $programRaw : null,
                'program_code' => $this->resolveProgramCodeFromLabel($programRaw),
                'year_level' => 'Year '.$m[3],
                'enrollment_status' => isset($m[4]) ? strtoupper($m[4]) : null,
                'courses' => [],
            ];
        }

        return $terms;
    }

    /**
     * @return 'course_history'|'grade_slip'|null
     */
    public function normalizeDocumentType(?string $documentType): ?string
    {
        if ($documentType === null) {
            return null;
        }

        $normalized = Str::lower(trim(str_replace([' ', '-'], '_', $documentType)));

        return match ($normalized) {
            'course_history', 'coursehistory' => 'course_history',
            'grade_slip', 'gradeslip', 'grade_slip_pdf' => 'grade_slip',
            default => null,
        };
    }

    /**
     * @param  list<array<string, mixed>>|null  $terms
     * @param  list<array<string, mixed>>|null  $courses
     * @return list<array<string, mixed>>
     */
    private function normalizeTermsInput(
        ?array $terms,
        ?array $courses,
        string $text,
        ?string $fallbackProgram,
    ): array {
        if (is_array($terms) && $terms !== []) {
            $out = [];
            foreach ($terms as $term) {
                if (! is_array($term)) {
                    continue;
                }
                $termCourses = is_array($term['courses'] ?? null)
                    ? array_values(array_filter($term['courses'], 'is_array'))
                    : [];
                // Allow header-only terms only when courses live on a sibling flat list.
                $out[] = [
                    'academic_term' => $term['academic_term'] ?? null,
                    'program_raw' => $term['program_raw'] ?? null,
                    'program_code' => $term['program_code'] ?? null,
                    'year_level' => $term['year_level'] ?? null,
                    'enrollment_status' => $term['enrollment_status'] ?? null,
                    'courses' => $termCourses,
                ];
            }
            $hasAnyCourses = collect($out)->contains(fn (array $t) => $t['courses'] !== []);
            if ($hasAnyCourses) {
                return $out;
            }
            // Header-only terms + flat courses: assign all courses to terms by order is unsafe;
            // fall through to groupBy annotations on courses.
        }

        if (is_array($courses) && $courses !== []) {
            $grouped = $this->groupCoursesIntoTerms($courses, $fallbackProgram);
            if ($grouped !== []) {
                return $grouped;
            }
        }

        // Recover term headers from linear OCR text when Python omitted terms[].
        // Never return header-only terms without courses — that would wipe grades in summarizeTerms.
        $fromText = $this->termsFromLinearText($text);
        if ($fromText === []) {
            return [];
        }

        $flatCourses = (is_array($courses) && $courses !== [])
            ? array_values(array_filter($courses, 'is_array'))
            : $this->coursesFromLinearText($text);

        if ($flatCourses === []) {
            return [];
        }

        $grouped = $this->groupCoursesIntoTerms($flatCourses, $fallbackProgram);
        if ($grouped !== []) {
            return $this->enrichGroupedTermsWithHeaders($grouped, $fromText);
        }

        // Single header: attach all linear courses (common CH extract without per-row terms).
        if (count($fromText) === 1) {
            $fromText[0]['courses'] = $flatCourses;

            return $fromText;
        }

        // Multiple headers without per-course term keys: keep grades via flat parse path.
        return [];
    }

    /**
     * Copy year_level / enrollment_status from text headers onto grouped terms.
     *
     * @param  list<array<string, mixed>>  $grouped
     * @param  list<array<string, mixed>>  $headers
     * @return list<array<string, mixed>>
     */
    private function enrichGroupedTermsWithHeaders(array $grouped, array $headers): array
    {
        $byTerm = [];
        foreach ($headers as $header) {
            $key = trim((string) ($header['academic_term'] ?? ''));
            if ($key !== '') {
                $byTerm[Str::lower($key)] = $header;
            }
        }
        foreach ($grouped as &$term) {
            $key = Str::lower(trim((string) ($term['academic_term'] ?? '')));
            if ($key === '' || ! isset($byTerm[$key])) {
                continue;
            }
            $header = $byTerm[$key];
            if (empty($term['year_level']) && ! empty($header['year_level'])) {
                $term['year_level'] = $header['year_level'];
            }
            if (empty($term['enrollment_status']) && ! empty($header['enrollment_status'])) {
                $term['enrollment_status'] = $header['enrollment_status'];
            }
            if (empty($term['program_raw']) && ! empty($header['program_raw'])) {
                $term['program_raw'] = $header['program_raw'];
            }
            if (empty($term['program_code']) && ! empty($header['program_code'])) {
                $term['program_code'] = $header['program_code'];
            }
        }
        unset($term);

        return $grouped;
    }

    /**
     * Group flat course rows that already carry academic_term / program_code into terms[].
     *
     * @param  list<array<string, mixed>>  $courses
     * @return list<array<string, mixed>>
     */
    private function groupCoursesIntoTerms(array $courses, ?string $fallbackProgram): array
    {
        $buckets = [];
        $order = [];
        $hasTermMeta = false;
        foreach ($courses as $course) {
            if (! is_array($course)) {
                continue;
            }
            $termKey = trim((string) ($course['academic_term'] ?? ''));
            $programKey = trim((string) ($course['program_code'] ?? $course['program_raw'] ?? ''));
            if ($termKey !== '' || $programKey !== '') {
                $hasTermMeta = true;
            }
            $key = ($termKey !== '' ? $termKey : '_').'|'.($programKey !== '' ? $programKey : '_');
            if (! isset($buckets[$key])) {
                $buckets[$key] = [
                    'academic_term' => $termKey !== '' ? $termKey : null,
                    'program_raw' => isset($course['program_raw']) ? (string) $course['program_raw'] : null,
                    'program_code' => $this->resolveProgramCodeFromLabel(
                        $course['program_code'] ?? $course['program_raw'] ?? $fallbackProgram
                    ),
                    'year_level' => isset($course['year_level']) ? (string) $course['year_level'] : null,
                    'enrollment_status' => null,
                    'courses' => [],
                ];
                $order[] = $key;
            }
            $buckets[$key]['courses'][] = $course;
        }

        if (! $hasTermMeta) {
            return [];
        }

        return array_map(fn (string $k) => $buckets[$k], $order);
    }

    private function passGradeForProgram(?string $programCode, float $defaultPass): float
    {
        $program = $this->findProgram($programCode);

        return (float) ($program?->pass_grade ?? $defaultPass);
    }

    private function findProgram(?string $programCode): ?AcademicProgram
    {
        if ($programCode === null || $programCode === '') {
            return null;
        }

        return AcademicProgram::query()
            ->whereRaw('UPPER(code) = ?', [Str::upper($programCode)])
            ->first();
    }

    private function looksLikeGrade(float $grade): bool
    {
        return $grade >= 1.0 && $grade <= 5.0;
    }

    private function looksLikeDropped(string $gradeText, string $remarksLower): bool
    {
        if ($remarksLower !== '' && preg_match('/\b(?:dropped|drop|drp|withdrawn|withdrawal)\b/i', $remarksLower)) {
            return true;
        }

        return $gradeText !== '' && preg_match('/^(?:drp|dropped|drop)$/i', $gradeText) === 1;
    }

    private function countDroppedRemarksInText(string $text): int
    {
        if (preg_match_all('/\bDropped\b|\bDRP\b/i', $text, $matches)) {
            return count($matches[0]);
        }

        return 0;
    }

    private function extractAcademicYear(string $text): ?string
    {
        if (preg_match('/\b(20\d{2})\s*[-–\/]\s*(20\d{2}|\d{2})\b/', $text, $m)) {
            $end = strlen($m[2]) === 2 ? substr($m[1], 0, 2).$m[2] : $m[2];

            return $m[1].'-'.$end;
        }

        return null;
    }

    private function extractSemesterLabel(string $text): ?string
    {
        if (preg_match('/\b(20\d{2}\s*[-–\/]\s*(?:20\d{2}|\d{2}))\s+(1st|2nd|3rd|Summer|Midyear)\b/iu', $text, $m)) {
            return trim(preg_replace('/\s+/', ' ', $m[0]));
        }
        if (preg_match('/\b(1st|2nd|3rd)\s+(?:Semester|Sem)\b/iu', $text, $m)) {
            return $m[0];
        }
        if (preg_match('/Academic\s*Term\s*[:#]?\s*(.+)$/imu', $text, $m)) {
            return trim(preg_replace('/\s+/', ' ', $m[1]));
        }

        return null;
    }

    private function extractYearLevel(string $text): ?string
    {
        if (preg_match('/Year\s*(1st|2nd|3rd|4th|5th|First|Second|Third|Fourth|[1-5])/iu', $text, $m)) {
            return 'Year '.$m[1];
        }

        return null;
    }

    private function extractSemesterGpa(string $text): ?float
    {
        if (preg_match('/Semester\s*GPA\s*[:#]?\s*([0-9]+(?:\.[0-9]+)?)/iu', $text, $m)) {
            return (float) $m[1];
        }
        if (preg_match('/\bGWA\s*[:#]?\s*([0-9]+(?:\.[0-9]+)?)/iu', $text, $m)) {
            return (float) $m[1];
        }
        if (preg_match('/\bGPA\s*[:#]?\s*([0-9]+(?:\.[0-9]+)?)/iu', $text, $m)) {
            return (float) $m[1];
        }

        return null;
    }
}
