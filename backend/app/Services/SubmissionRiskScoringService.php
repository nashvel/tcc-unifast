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
            ->pluck('text')
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
            // 30 pts per grade_slip document with missing/invalid/domain-mismatched QR.
            $signals['qr_code_invalid_or_domain_mismatch'] = 30;
        }

        $academics = $ocrSummary['_academics'] ?? null;
        if (is_array($academics) && ($academics['failed_count'] ?? 0) > PolicySetting::maxFailedSubjects()) {
            $signals['failed_subjects_over_limit'] = 25;
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
     * Evaluate eligibility from OCR grades.
     * Pass rule: fail if grade > program.pass_grade (default 3.0). Blank grades ignored.
     *
     * @param  array<string, array<string, mixed>>  $ocrSummary
     * @return array<string, mixed>
     */
    public function evaluateEligibility(Grantee $grantee, array $ocrSummary = []): array
    {
        $text = collect($ocrSummary)
            ->pluck('text')
            ->filter()
            ->implode("\n");

        if (trim($text) === '') {
            return [
                'status' => 'pending',
                'gwa' => 'pending',
                'failed_subjects' => 'pending',
                'failed_count' => null,
                'max_failed' => PolicySetting::maxFailedSubjects(),
                'pass_grade' => PolicySetting::defaultPassGrade(),
                'program_code' => $grantee->program,
                'dropped_subjects' => 'skipped',
                'note' => 'No OCR text available yet — eligibility pending staff review.',
                'grantee_id' => $grantee->id,
            ];
        }

        $parsed = $this->gradeParser->parse($text, $grantee->program);
        $maxFailed = PolicySetting::maxFailedSubjects();
        $failedCount = (int) $parsed['failed_count'];
        $status = $failedCount > $maxFailed ? 'fail' : 'pass';

        if ($parsed['grades'] === []) {
            $status = 'pending';
        }

        return [
            'status' => $status,
            'gwa' => $parsed['semester_gpa'] !== null ? (string) $parsed['semester_gpa'] : 'pending',
            'failed_subjects' => (string) $failedCount,
            'failed_count' => $failedCount,
            'max_failed' => $maxFailed,
            'pass_grade' => $parsed['pass_grade'],
            'program_code' => $parsed['program_code'],
            'program_matched' => $parsed['program_matched'],
            'academic_year' => $parsed['academic_year'],
            'semester_label' => $parsed['semester_label'],
            'year_level' => $parsed['year_level'],
            'grades' => $parsed['grades'],
            'dropped_subjects' => 'skipped',
            'note' => $status === 'pending'
                ? 'OCR text found but no numeric grades parsed — staff review required.'
                : sprintf(
                    'Pass if grade ≤ %.1f; fail if grade > %.1f. Failed %d / max %d.',
                    $parsed['pass_grade'],
                    $parsed['pass_grade'],
                    $failedCount,
                    $maxFailed,
                ),
            'grantee_id' => $grantee->id,
        ];
    }
}
