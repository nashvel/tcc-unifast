<?php

namespace App\Services;

use App\Models\Grantee;
use App\Models\PolicySetting;
use Illuminate\Support\Str;

class SubmissionRiskScoringService
{
    public function __construct(
        private readonly AcademicGradeParser $gradeParser,
    ) {}

    /**
     * @param  array<string, array<string, mixed>>  $ocrSummary
     * @param  array<string, mixed>  $gradeslipQr
     * @return array<string, int>
     */
    public function collectSignals(
        bool $identityFailed,
        array $ocrSummary,
        string $authenticityStatus,
        Grantee $grantee,
        array $gradeslipQr = [],
    ): array {
        $signals = [];

        if ($identityFailed) {
            $signals['identity_check_failed'] = 50;
        }

        if (in_array($authenticityStatus, ['failed', 'suspicious'], true)) {
            $signals['pdf_metadata_tampering'] = 30;
        }

        if ($this->pdfMetadataSuspicious($ocrSummary)) {
            $signals['pdf_metadata_tampering'] = 30;
        }

        $combinedText = collect($ocrSummary)
            ->filter(fn ($row, $key) => is_array($row) && $key !== '_academics')
            ->map(fn ($row) => (string) ($row['raw_text'] ?? $row['text'] ?? ''))
            ->filter()
            ->implode("\n");
        $haystack = Str::lower($combinedText);
        $name = Str::lower((string) ($grantee->kycProfile?->full_name ?: $grantee->full_name));
        $studentId = Str::lower((string) $grantee->student_id);

        if ($combinedText !== '' && $name !== '' && ! str_contains($haystack, $name)) {
            $signals['name_or_id_mismatch'] = 40;
        } elseif ($combinedText !== '' && $studentId !== '' && ! str_contains($haystack, $studentId)) {
            $signals['name_or_id_mismatch'] = 40;
        }

        if ($this->gradeslipQrInvalid($gradeslipQr)) {
            $signals['qr_code_invalid_or_domain_mismatch'] = 30;
        }

        $academics = $ocrSummary['_academics'] ?? null;
        $maxFailed = PolicySetting::maxFailedSubjects();
        // Retention = numeric fails + dropped (GS blanks + CH pending ignored).
        if (is_array($academics)) {
            $retention = (int) ($academics['retention_count']
                ?? ((int) ($academics['failed_count'] ?? 0) + (int) ($academics['dropped_count'] ?? 0)));
            if ($retention >= $maxFailed) {
                $signals['failed_subjects_over_limit'] = 25;
            }
        }

        return $signals;
    }

    /**
     * @param  array<string, mixed>  $ocrSummary
     */
    private function pdfMetadataSuspicious(array $ocrSummary): bool
    {
        foreach (['course_history', 'grade_slip'] as $slot) {
            $analysis = $ocrSummary[$slot]['pdf_metadata_analysis'] ?? null;
            if (is_array($analysis) && ($analysis['suspicious'] ?? false)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $gradeslipQr
     */
    private function gradeslipQrInvalid(array $gradeslipQr): bool
    {
        if ($gradeslipQr === []) {
            return false;
        }

        $status = (string) ($gradeslipQr['status'] ?? '');
        if (in_array($status, ['skipped', 'unavailable', 'script_missing', 'process_failed', 'invalid_json'], true)) {
            return false;
        }

        return ! (bool) ($gradeslipQr['success'] ?? false);
    }

    /**
     * @param  array<string, int>  $signals
     */
    public function score(array $signals): int
    {
        return (int) array_sum($signals);
    }

    public function badge(int $score): string
    {
        return match (true) {
            $score >= 80 => 'block',
            $score >= 50 => 'high',
            $score >= 21 => 'medium',
            default => 'low',
        };
    }

    /**
     * Evaluate eligibility from OCR grades / structured courses.
     *
     * Setting `max_failed_subjects_per_semester`: not eligible when
     * failed + dropped count >= that value.
     * Grade-slip blank grades are ignored for eligibility (blank_count for staff).
     * Course-history blanks in the pending window (current + 2 prior terms) are
     * pending (ignored); older-term blanks count as dropped toward retention.
     *
     * Retention decision is driven by Course History (full history) when present;
     * Grade Slip is supplemental (current-term review) and only used when CH is missing.
     *
     * @param  array<string, array<string, mixed>>  $ocrSummary
     * @return array<string, mixed>
     */
    public function evaluateEligibility(Grantee $grantee, array $ocrSummary = []): array
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

        $parsed = $this->gradeParser->parse(
            $text,
            $grantee->program,
            $courses !== [] ? $courses : null,
            $documentType,
            $terms !== [] ? $terms : null,
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
            ? sprintf(' Pending grades ignored for eligibility (%d pending in current/recent terms).', $pendingCount)
            : ($blankCount > 0
                ? sprintf(' Blank grades ignored for eligibility (%d blank noted for staff).', $blankCount)
                : ($documentType === 'course_history'
                    ? ' Older-term Course History blanks counted as dropped; recent blanks are pending.'
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

        $gradeSlipSupplement = null;
        if ($documentType === 'course_history') {
            $gsCourses = $ocrSummary['grade_slip']['courses'] ?? null;
            $gsTerms = $ocrSummary['grade_slip']['terms'] ?? null;
            if ((is_array($gsCourses) && $gsCourses !== []) || (is_array($gsTerms) && $gsTerms !== [])) {
                $gsParsed = $this->gradeParser->parse(
                    (string) ($ocrSummary['grade_slip']['raw_text'] ?? $ocrSummary['grade_slip']['text'] ?? ''),
                    $grantee->program,
                    is_array($gsCourses) && $gsCourses !== [] ? array_values(array_filter($gsCourses, 'is_array')) : null,
                    'grade_slip',
                    is_array($gsTerms) && $gsTerms !== [] ? array_values(array_filter($gsTerms, 'is_array')) : null,
                );
                $gradeSlipSupplement = [
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
}
