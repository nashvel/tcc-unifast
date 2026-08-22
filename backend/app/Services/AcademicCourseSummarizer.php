<?php

namespace App\Services;

use App\Models\AcademicProgram;
use App\Models\PolicySetting;
use Illuminate\Support\Str;

class AcademicCourseSummarizer
{
    public function __construct(
        private readonly AcademicGradeTextParser $textParser = new AcademicGradeTextParser,
        private readonly AcademicTermAnalyzer $termAnalyzer = new AcademicTermAnalyzer,
    ) {}

    /**
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
        $documentType = $this->textParser->normalizeDocumentType($documentType);
        $defaultPass ??= (float) PolicySetting::defaultPassGrade();
        $allGrades = [];
        $allCourses = [];
        $normalizedTerms = [];
        $blank = 0;
        $pending = 0;
        $dropped = 0;
        $numericFailed = 0;
        $latestProgram = $this->textParser->resolveProgramCodeFromLabel($fallbackProgram);
        $anyMatched = false;

        $pendingKeys = $this->termAnalyzer->pendingTermKeys($terms, $gradeSlipTerm);

        foreach ($terms as $term) {
            if (! is_array($term)) {
                continue;
            }
            $programRaw = isset($term['program_raw']) ? trim((string) $term['program_raw']) : null;
            $programCode = $this->textParser->resolveProgramCodeFromLabel(
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
            $blankMode = $this->termAnalyzer->blankModeForTerm($documentType, $academicTerm, $pendingKeys);
            $termSummary = $this->summarizeCourses($termCourses, $passGrade, $documentType, $programCode, $blankMode);

            foreach ($termSummary['courses'] as $course) {
                $course['academic_term'] = $term['academic_term'] ?? null;
                $course['program_code'] = $programCode;
                $course['program_raw'] = $programRaw ?: ($term['program_raw'] ?? null);
                $course['year_level'] = $term['year_level'] ?? null;
                $course['pass_grade'] = $passGrade;
                $allCourses[] = $course;
            }
            foreach ($termSummary['grades'] as $grade) {
                $allGrades[] = $grade;
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

        usort($normalizedTerms, function (array $a, array $b): int {
            return $this->termAnalyzer->termSortKey($a['academic_term'] ?? null)
                <=> $this->termAnalyzer->termSortKey($b['academic_term'] ?? null);
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
     * @param  'informational'|'pending'|'dropped'|null  $blankMode
     * @return array<string, mixed>
     */
    public function summarizeCourses(
        array $courses,
        float $passGrade,
        ?string $documentType = null,
        ?string $programCode = null,
        ?string $blankMode = null,
    ): array {
        $documentType = $this->textParser->normalizeDocumentType($documentType);
        $blankMode ??= $documentType === 'course_history' ? 'pending' : 'informational';
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

            $rowProgram = $this->textParser->resolveProgramCodeFromLabel($course['program_code'] ?? $course['program_raw'] ?? null)
                ?: $programCode;
            $rowPass = isset($course['pass_grade']) && is_numeric($course['pass_grade'])
                ? (float) $course['pass_grade']
                : $this->passGradeForProgram($rowProgram, $passGrade);

            $rawGrade = $course['grade'] ?? null;
            if (isset($course['re_grade']) && is_string($course['re_grade']) && trim($course['re_grade']) !== '') {
                $rawGrade = $course['re_grade'];
            }
            $gradeText = is_string($rawGrade) || is_numeric($rawGrade) ? trim((string) $rawGrade) : '';
            $remarks = trim((string) ($course['remarks'] ?? ''));
            $isDropped = $this->looksLikeDropped($gradeText, strtolower($remarks));
            $isBlankGrade = $gradeText === '' || preg_match('/^(inc|incomplete|ng|no\s*grade|-|—|–)$/iu', $gradeText) === 1;
            $failReason = null;

            if ($isDropped) {
                $dropped++;
                $failReason = 'dropped';
            } elseif ($isBlankGrade && $blankMode === 'dropped') {
                $dropped++;
                $failReason = 'dropped';
                if ($remarks === '') {
                    $remarks = 'Dropped';
                }
            } elseif ($isBlankGrade && $blankMode === 'pending') {
                $pending++;
                $failReason = 'pending';
                if ($remarks === '') {
                    $remarks = 'Pending';
                }
            } elseif ($isBlankGrade) {
                $blank++;
                $failReason = 'blank';
            } elseif (is_numeric($gradeText)) {
                $candidate = (float) $gradeText;
                if ($this->textParser->looksLikeGrade($candidate)) {
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
                'counts_as_fail' => $failReason !== null && ! in_array($failReason, ['blank', 'pending'], true),
                'fail_reason' => $failReason,
            ];
        }

        return [
            'grades' => $grades,
            'courses' => $normalized,
            'failed_count' => $numericFailed,
            'blank_count' => $blank,
            'pending_count' => $pending,
            'dropped_count' => $dropped,
            'numeric_failed_count' => $numericFailed,
            'retention_count' => $numericFailed + $dropped,
            'program_code' => $programCode,
            'pass_grade' => $passGrade,
            'program_matched' => $this->findProgram($programCode) !== null,
        ];
    }

    public function passGradeForProgram(?string $programCode, float $defaultPass): float
    {
        $program = $this->findProgram($programCode);

        return (float) ($program?->pass_grade ?? $defaultPass);
    }

    public function findProgram(?string $programCode): ?AcademicProgram
    {
        if ($programCode === null || $programCode === '') {
            return null;
        }

        return AcademicProgram::query()
            ->whereRaw('UPPER(code) = ?', [Str::upper($programCode)])
            ->first();
    }

    private function looksLikeDropped(string $gradeText, string $remarksLower): bool
    {
        if ($remarksLower !== '' && preg_match('/\b(?:dropped|drop|drp|withdrawn|withdrawal)\b/i', $remarksLower)) {
            return true;
        }

        return $gradeText !== '' && preg_match('/^(?:drp|dropped|drop)$/i', $gradeText) === 1;
    }
}
