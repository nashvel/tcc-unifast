<?php

namespace App\Services;

use App\Models\Grantee;
use App\Models\PolicySetting;
use Illuminate\Support\Str;

class SubmissionRiskScoringService
{
    public function __construct(
        private readonly AcademicGradeParser $gradeParser,
        private readonly SubmissionEligibilityEvaluator $eligibilityEvaluator,
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
        // Delegated: SubmissionEligibilityEvaluator owns the retention rules
        // (Course-History preference, pending-vs-dropped blanks, Grade Slip
        // supplement). Kept as a thin pass-through so existing call sites and the
        // risk-scoring API stay stable.
        return $this->eligibilityEvaluator->evaluate($grantee, $ocrSummary);
    }
}
