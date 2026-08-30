<?php

namespace App\Services;

use App\Models\BatchNotification;
use App\Models\DocumentSubmission;
use App\Models\Grantee;
use App\Models\PolicySetting;
use App\Models\SubmissionPipelineResult;

class EligibilityPresenter
{
    /** Identity is verified once during onboarding; the vault has no school_id slot. */
    private const REQUIRED_SLOTS = ['course_history', 'grade_slip', 'specimen_signatures'];

    /**
     * @return array<string, mixed>
     */
    public function listRow(Grantee $grantee): array
    {
        $detail = $this->detail($grantee);

        return [
            'id' => $detail['id'],
            'studentNo' => $detail['studentNo'],
            'name' => $detail['name'],
            'batch' => $detail['batch'],
            'batch_id' => $detail['batch_id'],
            'passed' => $detail['passed'],
            'status' => $detail['status'],
            'missing' => $detail['missing'],
            'notice' => $detail['notice'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(Grantee $grantee): array
    {
        $pipeline = $grantee->pipelineResults->first();
        if (! $pipeline instanceof SubmissionPipelineResult) {
            $pipeline = SubmissionPipelineResult::query()
                ->where('grantee_id', $grantee->id)
                ->latest('id')
                ->first();
        }

        $eligibility = is_array($pipeline?->eligibility) ? $pipeline->eligibility : [];
        $batch = $grantee->batch;
        $batchLabel = $batch
            ? trim(($batch->name ?: '').' '.trim(($batch->academic_year ?? '').' '.($batch->semester ?? '')))
            : 'Unassigned batch';
        if ($batch && $batch->name) {
            $batchLabel = $batch->name;
        }

        $maxFailed = (int) ($eligibility['max_failed'] ?? PolicySetting::maxFailedSubjects());
        $docsOk = $this->documentsComplete($grantee);
        $enrolledOk = $grantee->batch_id !== null;
        $retentionOk = $this->retentionPassed($grantee, $eligibility);

        $criteria = [
            [
                'label' => 'Enrolled in current activation batch',
                'passed' => $enrolledOk,
                'note' => $enrolledOk
                    ? "Enrollment verified for {$batchLabel}."
                    : 'Grantee is not assigned to an activation batch.',
            ],
            [
                'label' => 'Required documents submitted for current batch',
                'passed' => $docsOk,
                'note' => $docsOk
                    ? 'Course History, Grade Slip, and ID (Back-to-Back) & Specimen are on file for this batch.'
                    : 'One or more required vault slots are missing for this batch.',
            ],
            [
                'label' => 'Academic retention (max failed subjects)',
                'passed' => $retentionOk,
                'note' => $this->retentionNote($eligibility, $maxFailed, $retentionOk),
            ],
        ];

        $passedCount = collect($criteria)->where('passed', true)->count();
        $status = $this->mapStatus($grantee, $eligibility, $enrolledOk, $docsOk, $retentionOk);
        $missing = $this->missingMessage($status, $criteria, $eligibility);

        return [
            'id' => $grantee->id,
            'studentNo' => (string) ($grantee->student_number ?: $grantee->student_id),
            'name' => (string) $grantee->full_name,
            'batch' => $batchLabel,
            'batch_id' => $grantee->batch_id,
            'passed' => "{$passedCount} / 3",
            'status' => $status,
            'missing' => $missing,
            'notice' => match ($status) {
                'Eligible' => 'No notice needed',
                'Needs update' => 'Needs reminder',
                default => 'Needs notice',
            },
            'max_failed_subjects_per_semester' => $maxFailed,
            'criteria' => $criteria,
            'eligibility' => $eligibility,
            'risk_score' => $pipeline?->risk_score,
            'risk_badge' => $pipeline?->risk_badge,
            'pipeline_status' => $pipeline?->status,
            'notices' => $this->noticeHistory($grantee),
        ];
    }

    private function documentsComplete(Grantee $grantee): bool
    {
        if (! $grantee->batch_id) {
            return false;
        }

        $keys = DocumentSubmission::query()
            ->where('grantee_id', $grantee->id)
            ->where('batch_id', $grantee->batch_id)
            ->whereNotNull('stored_path')
            ->pluck('slot_key')
            ->unique()
            ->all();

        foreach (self::REQUIRED_SLOTS as $slot) {
            if (! in_array($slot, $keys, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $eligibility
     */
    private function retentionPassed(Grantee $grantee, array $eligibility): bool
    {
        $pipelineStatus = (string) ($eligibility['status'] ?? '');
        if ($pipelineStatus === 'pass') {
            return true;
        }
        if ($pipelineStatus === 'fail') {
            return false;
        }

        return $grantee->status === 'eligible';
    }

    /**
     * @param  array<string, mixed>  $eligibility
     */
    private function retentionNote(array $eligibility, int $maxFailed, bool $passed): string
    {
        $source = (string) ($eligibility['source'] ?? $eligibility['document_type'] ?? '');
        $sourceLabel = $source === 'course_history'
            ? ' from Course History'
            : ($source === 'grade_slip' ? ' from Grade Slip (CH missing)' : '');

        if ($passed) {
            $failed = $eligibility['failed_subjects'] ?? $eligibility['failed_count'] ?? '0';
            $dropped = $eligibility['dropped_count'] ?? $eligibility['dropped_subjects'] ?? '0';
            $retention = $eligibility['retention_count'] ?? null;
            $counts = $retention !== null
                ? "{$failed} failed + {$dropped} dropped = {$retention}"
                : "Failed subjects: {$failed}";

            return "Within Settings limit (max {$maxFailed} failed+dropped subject(s)){$sourceLabel}. {$counts}.";
        }

        if (($eligibility['status'] ?? '') === 'pending' || $eligibility === []) {
            return "Academic retention pending OCR/pipeline results. Settings allow up to {$maxFailed} failed+dropped subject(s). Course History drives eligibility when available.";
        }

        return (string) ($eligibility['note']
            ?: "Settings allow up to {$maxFailed} failed+dropped subject(s). This grantee exceeded the limit{$sourceLabel}.");
    }

    /**
     * @param  array<string, mixed>  $eligibility
     */
    private function mapStatus(
        Grantee $grantee,
        array $eligibility,
        bool $enrolledOk,
        bool $docsOk,
        bool $retentionOk,
    ): string {
        if ($grantee->status === 'not_eligible' || ($eligibility['status'] ?? '') === 'fail') {
            return 'Not eligible';
        }

        if ($grantee->status === 'eligible' && $enrolledOk && $docsOk && $retentionOk) {
            return 'Eligible';
        }

        if (($eligibility['status'] ?? '') === 'pass' && $enrolledOk && $docsOk) {
            return 'Eligible';
        }

        return 'Needs update';
    }

    /**
     * @param  list<array{label: string, passed: bool, note: string}>  $criteria
     * @param  array<string, mixed>  $eligibility
     */
    private function missingMessage(string $status, array $criteria, array $eligibility): string
    {
        if ($status === 'Eligible') {
            return 'No missing batch submissions or retention issues.';
        }

        $failed = collect($criteria)->first(fn (array $row) => ! $row['passed']);
        if ($failed) {
            return $failed['note'];
        }

        return (string) ($eligibility['note'] ?? 'Submission needs staff review.');
    }

    /**
     * @return list<array{date: string, title: string, status: string}>
     */
    private function noticeHistory(Grantee $grantee): array
    {
        if (! $grantee->user_id) {
            return [];
        }

        return BatchNotification::query()
            ->where('user_id', $grantee->user_id)
            ->where('type', 'eligibility_notice')
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (BatchNotification $n) => [
                'date' => $n->created_at?->toFormattedDateString() ?? '',
                'title' => $n->title,
                'status' => $n->read_at ? 'Read by student' : 'Delivered by portal notification',
            ])
            ->all();
    }
}
