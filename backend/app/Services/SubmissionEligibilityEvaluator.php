<?php

namespace App\Services;

use App\Models\Grantee;
use App\Models\PolicySetting;

class SubmissionEligibilityEvaluator
{
    public function __construct(
        private readonly AcademicGradeParser $gradeParser,
    ) {}

    /**
     * Evaluate eligibility from OCR grades / structured courses.
     *
     * Setting `max_failed_subjects_per_semester` (overall retention max, default 3):
     * not eligible when overall failed + dropped count >= that value.
     * Failed and dropped each count 1; pending blanks do not count.
     * Grade-slip blank grades are ignored for eligibility (blank_count for staff).
     * Course-history blanks on the Grade Slip term and any newer enrollment term are
     * pending (ignored); older-term blanks count as dropped toward retention.
     *
     * Retention decision is driven by Course History (full history, all terms) when present;
     * Grade Slip anchors the pending window and is only used as retention fallback when CH is missing.
     *
     * @param  array<string, array<string, mixed>>  $ocrSummary
     * @return array<string, mixed>
     */
    public function evaluate(Grantee $grantee, array $ocrSummary = []): array
    {
        $preferred = $this->preferredCourseSource($ocrSummary);
        $courses = $preferred['courses'];
        $terms = $preferred['terms'];
        $documentType = $preferred['document_type'];
        $text = '';
        if ($documentType !== null && isset($ocrSummary[$documentType]) && is_array($ocrSummary[$documentType])) {
            $text = (string) ($ocrSummary[$documentType]['raw_text']
                ?? $ocrSummary[$documentType]['text']
                ?? '');
        }
        if (trim($text) === '') {
            $text = collect($ocrSummary)
                ->filter(fn ($row, $key) => is_array($row) && $key !== '_academics')
                ->map(fn ($row) => (string) ($row['raw_text'] ?? $row['text'] ?? ''))
                ->filter()
                ->implode("\n");
        }

        if (trim($text) === '' && $courses === [] && $terms === []) {
            return [
                'status' => 'pending',
                'gwa' => 'pending',
                'failed_subjects' => 'pending',
                'failed_count' => null,
                'blank_count' => null,
                'pending_count' => null,
                'dropped_count' => null,
                'retention_count' => null,
                'max_failed' => PolicySetting::maxFailedSubjects(),
                'pass_grade' => PolicySetting::defaultPassGrade(),
                'program_code' => $grantee->program,
                'dropped_subjects' => 'pending',
                'note' => 'No OCR text available yet — eligibility pending staff review.',
                'grantee_id' => $grantee->id,
                'source' => null,
            ];
        }

        $gradeSlipTerm = null;
        if ($documentType === 'course_history' && isset($ocrSummary['grade_slip']) && is_array($ocrSummary['grade_slip'])) {
            $gradeSlipTerm = $this->gradeParser->resolveGradeSlipTerm(
                $ocrSummary['grade_slip'],
                $terms !== [] ? $terms : null,
            );
        }

        $parsed = $this->gradeParser->parse(
            $text,
            $grantee->program,
            $courses !== [] ? $courses : null,
            $documentType,
            $terms !== [] ? $terms : null,
            $gradeSlipTerm,
        );
        $maxFailed = PolicySetting::maxFailedSubjects();
        $failedCount = (int) $parsed['failed_count'];
        $blankCount = (int) $parsed['blank_count'];
        $pendingCount = (int) ($parsed['pending_count'] ?? 0);
        $droppedCount = (int) $parsed['dropped_count'];
        $retentionCount = (int) ($parsed['retention_count'] ?? ($failedCount + $droppedCount));
        $status = $retentionCount >= $maxFailed ? 'fail' : 'pass';

        if ($parsed['grades'] === [] && $droppedCount === 0 && $blankCount === 0 && $pendingCount === 0 && ($parsed['courses'] ?? []) === []) {
            $status = 'pending';
        }

        $sourceNote = $documentType === 'course_history'
            ? ' Retention counted from Course History (full history).'
            : ($documentType === 'grade_slip'
                ? ' Course History missing — Grade Slip used as fallback.'
                : '');

        $blankNote = $pendingCount > 0
            ? sprintf(' Pending grades ignored for eligibility (%d pending on GS term / current enrollment).', $pendingCount)
            : ($blankCount > 0
                ? sprintf(' Blank grades ignored for eligibility (%d blank noted for staff).', $blankCount)
                : ($documentType === 'course_history'
                    ? ' Older Course History blanks counted as dropped; GS term and newer enrollment blanks are pending.'
                    : ' Blank grades ignored for eligibility.'));

        $note = match ($status) {
            'pending' => 'OCR text found but no course rows/grades parsed — staff review required.',
            'fail' => sprintf(
                'Not eligible: %d failed + %d dropped = %d subjects (max %d).%s%s Setting: max_failed_subjects_per_semester.',
                $failedCount,
                $droppedCount,
                $retentionCount,
                $maxFailed,
                $blankNote,
                $sourceNote,
            ),
            default => sprintf(
                'Within retention limit: %d failed + %d dropped = %d of max %d.%s%s Setting: max_failed_subjects_per_semester.',
                $failedCount,
                $droppedCount,
                $retentionCount,
                $maxFailed,
                $blankNote,
                $sourceNote,
            ),
        };

        $gradeSlipSupplement = $this->gradeSlipSupplement($documentType, $grantee, $ocrSummary);

        return [
            'status' => $status,
            'gwa' => $parsed['semester_gpa'] !== null ? (string) $parsed['semester_gpa'] : 'pending',
            'failed_subjects' => (string) $failedCount,
            'failed_count' => $failedCount,
            'blank_count' => $blankCount,
            'pending_count' => $pendingCount,
            'dropped_count' => $droppedCount,
            'numeric_failed_count' => (int) ($parsed['numeric_failed_count'] ?? $failedCount),
            'retention_count' => $retentionCount,
            'max_failed' => $maxFailed,
            'pass_grade' => $parsed['pass_grade'],
            'program_code' => $parsed['program_code'],
            'program_matched' => $parsed['program_matched'],
            'academic_year' => $parsed['academic_year'],
            'semester_label' => $parsed['semester_label'],
            'year_level' => $parsed['year_level'],
            'grades' => $parsed['grades'],
            'courses' => $parsed['courses'],
            'terms' => $parsed['terms'] ?? [],
            'document_type' => $documentType,
            'source' => $documentType,
            'grade_slip_supplement' => $gradeSlipSupplement,
            'dropped_subjects' => (string) $droppedCount,
            'note' => $note,
            'grantee_id' => $grantee->id,
        ];
    }

    /**
     * Prefer Course History (full history) for retention; fall back to grade_slip.
     *
     * @param  array<string, array<string, mixed>>  $ocrSummary
     * @return array{
     *   courses: list<array<string, mixed>>,
     *   terms: list<array<string, mixed>>,
     *   document_type: 'course_history'|'grade_slip'|null
     * }
     */
    private function preferredCourseSource(array $ocrSummary): array
    {
        foreach (['course_history', 'grade_slip'] as $slot) {
            $terms = $ocrSummary[$slot]['terms'] ?? null;
            $courses = $ocrSummary[$slot]['courses'] ?? null;
            $normalizedTerms = is_array($terms)
                ? array_values(array_filter($terms, 'is_array'))
                : [];
            $normalizedCourses = is_array($courses)
                ? array_values(array_filter($courses, 'is_array'))
                : [];
            if ($normalizedTerms !== [] || $normalizedCourses !== []) {
                return [
                    'courses' => $normalizedCourses,
                    'terms' => $normalizedTerms,
                    'document_type' => $slot,
                ];
            }
        }

        $fromAcademics = $ocrSummary['_academics']['courses'] ?? null;
        $fromTerms = $ocrSummary['_academics']['terms'] ?? null;
        $academicsType = $ocrSummary['_academics']['document_type'] ?? null;
        if ((is_array($fromAcademics) && $fromAcademics !== []) || (is_array($fromTerms) && $fromTerms !== [])) {
            return [
                'courses' => is_array($fromAcademics) ? array_values(array_filter($fromAcademics, 'is_array')) : [],
                'terms' => is_array($fromTerms) ? array_values(array_filter($fromTerms, 'is_array')) : [],
                'document_type' => is_string($academicsType) ? $academicsType : null,
            ];
        }

        return ['courses' => [], 'terms' => [], 'document_type' => null];
    }

    /**
     * @param  array<string, array<string, mixed>>  $ocrSummary
     * @return array<string, mixed>|null
     */
    private function gradeSlipSupplement(?string $documentType, Grantee $grantee, array $ocrSummary): ?array
    {
        if ($documentType !== 'course_history') {
            return null;
        }

        $gsCourses = $ocrSummary['grade_slip']['courses'] ?? null;
        $gsTerms = $ocrSummary['grade_slip']['terms'] ?? null;
        if ((! is_array($gsCourses) || $gsCourses === []) && (! is_array($gsTerms) || $gsTerms === [])) {
            return null;
        }

        $gsParsed = $this->gradeParser->parse(
            (string) ($ocrSummary['grade_slip']['raw_text'] ?? $ocrSummary['grade_slip']['text'] ?? ''),
            $grantee->program,
            is_array($gsCourses) && $gsCourses !== [] ? array_values(array_filter($gsCourses, 'is_array')) : null,
            'grade_slip',
            is_array($gsTerms) && $gsTerms !== [] ? array_values(array_filter($gsTerms, 'is_array')) : null,
        );

        return [
            'failed_count' => (int) $gsParsed['failed_count'],
            'blank_count' => (int) $gsParsed['blank_count'],
            'pending_count' => (int) ($gsParsed['pending_count'] ?? 0),
            'dropped_count' => (int) $gsParsed['dropped_count'],
            'retention_count' => (int) ($gsParsed['retention_count'] ?? 0),
            'program_code' => $gsParsed['program_code'],
            'note' => 'Grade Slip shown for current-term review only; eligibility uses Course History.',
        ];
    }
}
