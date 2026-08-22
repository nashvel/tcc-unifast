<?php

namespace App\Services;

use App\Models\AcademicProgram;
use App\Models\PolicySetting;
use Illuminate\Support\Str;

class AcademicGradeParser
{
    private ?AcademicGradeTextParser $textParser = null;

    private ?AcademicTermAnalyzer $termAnalyzer = null;

    private ?AcademicCourseSummarizer $courseSummarizer = null;

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
        return $this->courseSummarizer()->summarizeTerms(
            $terms,
            $documentType,
            $fallbackProgram,
            $defaultPass,
            $gradeSlipTerm,
        );
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
        return $this->courseSummarizer()->summarizeCourses(
            $courses,
            $passGrade,
            $documentType,
            $programCode,
            $blankMode,
        );
    }

    /**
     * Sort key for academic terms like "2025-2026 2nd", "2025-2026 Summer".
     * Higher = newer. Unknown terms sort to 0.
     */
    public function termSortKey(?string $academicTerm): int
    {
        return $this->termAnalyzer()->termSortKey($academicTerm);
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
        return $this->termAnalyzer()->pendingTermKeys($terms, $gradeSlipTerm);
    }

    /**
     * @param  list<array<string, mixed>>  $terms
     */
    public function termHasBlankGrades(array $terms, string $academicTermCanon): bool
    {
        return $this->termAnalyzer()->termHasBlankGrades($terms, $academicTermCanon);
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
        return $this->termAnalyzer()->resolveGradeSlipTerm($gradeSlipParsed, $courseHistoryTerms);
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
        return $this->termAnalyzer()->inferGradeSlipTermFromCourseOverlap($gsCourses, $chTerms);
    }

    /**
     * True when Grade Slip looks like current-enrollment (no graded rows).
     *
     * @param  array<string, mixed>  $gradeSlipParsed
     */
    public function gradeSlipLooksLikeEmptyEnrollment(array $gradeSlipParsed): bool
    {
        return $this->termAnalyzer()->gradeSlipLooksLikeEmptyEnrollment($gradeSlipParsed);
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
        return $this->termAnalyzer()->crossCheckChBlanksAgainstGradeSlip(
            $chTerms,
            $chCourses,
            $gsCourses,
            $gradeSlipTerm,
        );
    }

    /**
     * @param  'course_history'|'grade_slip'|null  $documentType
     * @param  list<string>  $pendingKeys
     * @return 'informational'|'pending'|'dropped'
     */
    private function blankModeForTerm(?string $documentType, string $academicTerm, array $pendingKeys): string
    {
        return $this->termAnalyzer()->blankModeForTerm($documentType, $academicTerm, $pendingKeys);
    }

    public function extractProgramCode(string $text): ?string
    {
        return $this->textParser()->extractProgramCode($text);
    }

    /**
     * Map labels like "BSED Filipino" / "BSIT" to academic_programs.code.
     */
    public function resolveProgramCodeFromLabel(?string $value): ?string
    {
        return $this->textParser()->resolveProgramCodeFromLabel($value);
    }

    /** @return list<float> */
    public function extractNumericGrades(string $text): array
    {
        return $this->textParser()->extractNumericGrades($text);
    }

    /** @return list<array<string, mixed>> */
    public function coursesFromLinearText(string $text): array
    {
        return $this->textParser()->coursesFromLinearText($text);
    }

    /**
     * Parse CH term headers from linear OCR text into terms with empty courses
     * (used when Python did not return terms[] but text still has block headers).
     *
     * @return list<array<string, mixed>>
     */
    public function termsFromLinearText(string $text): array
    {
        return $this->textParser()->termsFromLinearText($text);
    }

    /**
     * @return 'course_history'|'grade_slip'|null
     */
    public function normalizeDocumentType(?string $documentType): ?string
    {
        return $this->textParser()->normalizeDocumentType($documentType);
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
        return $this->courseSummarizer()->passGradeForProgram($programCode, $defaultPass);
    }

    private function findProgram(?string $programCode): ?AcademicProgram
    {
        return $this->courseSummarizer()->findProgram($programCode);
    }

    private function countDroppedRemarksInText(string $text): int
    {
        return $this->textParser()->countDroppedRemarksInText($text);
    }

    private function extractAcademicYear(string $text): ?string
    {
        return $this->textParser()->extractAcademicYear($text);
    }

    private function extractSemesterLabel(string $text): ?string
    {
        return $this->textParser()->extractSemesterLabel($text);
    }

    private function extractYearLevel(string $text): ?string
    {
        return $this->textParser()->extractYearLevel($text);
    }

    private function extractSemesterGpa(string $text): ?float
    {
        return $this->textParser()->extractSemesterGpa($text);
    }

    private function textParser(): AcademicGradeTextParser
    {
        return $this->textParser ??= new AcademicGradeTextParser;
    }

    private function termAnalyzer(): AcademicTermAnalyzer
    {
        return $this->termAnalyzer ??= new AcademicTermAnalyzer;
    }

    private function courseSummarizer(): AcademicCourseSummarizer
    {
        return $this->courseSummarizer ??= new AcademicCourseSummarizer;
    }
}
