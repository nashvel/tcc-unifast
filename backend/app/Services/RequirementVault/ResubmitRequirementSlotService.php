<?php

namespace App\Services\RequirementVault;

use App\Jobs\ProcessRequirementSubmissionPipeline;
use App\Models\AuditLog;
use App\Models\DocumentSubmission;
use App\Models\Grantee;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ResubmitRequirementSlotService
{
    private const SCHOOL_ID_SLOT = 'school_id';

    public function __construct(
        private readonly ConfirmRequirementPackageService $confirmPackage,
    ) {}

    /**
     * @return array{submission: DocumentSubmission, grantee: Grantee}
     */
    public function resubmit(User $user, Grantee $grantee, int $batchId, string $slotKey, ?string $ipAddress): array
    {
        $this->assertCanMutateVault($grantee, $slotKey);

        $submission = $this->requireSlot((string) $user->student_id, $batchId, $slotKey);
        if ($submission->status !== 'draft') {
            throw ValidationException::withMessages([
                'slot_key' => 'Replace the returned document before resubmitting this slot.',
            ]);
        }

        if (($grantee->submission_status ?? '') !== 'resubmission_requested') {
            throw ValidationException::withMessages([
                'submission' => 'Single-slot resubmit is only available after staff requests a resubmission.',
            ]);
        }

        if ($slotKey === self::SCHOOL_ID_SLOT) {
            $this->confirmPackage->assertSchoolIdFaceBound($grantee, $submission);
        }

        $submission->update(['status' => 'pending_review']);

        $stillOpen = DocumentSubmission::query()
            ->where('grantee_id', $grantee->id)
            ->where('batch_id', $batchId)
            ->whereIn('status', ['resubmission', 'draft'])
            ->exists();

        $grantee->update([
            'submission_status' => $stillOpen ? 'resubmission_requested' : 'docs_submitted',
            'submitted_at' => $grantee->submitted_at ?? now(),
        ]);

        ProcessRequirementSubmissionPipeline::dispatch(
            $grantee->id,
            $batchId,
            false,
        );

        AuditLog::create([
            'actor' => $user->name,
            'role' => 'Student',
            'action' => 'requirement_slot_resubmitted',
            'module' => 'Requirements Submission',
            'target' => "Submission #{$submission->id}",
            'context' => [
                'batch_id' => $batchId,
                'slot_key' => $slotKey,
                'pipeline' => 'queued',
            ],
            'ip_address' => $ipAddress,
        ]);

        return [
            'submission' => $submission->fresh(),
            'grantee' => $grantee->fresh(),
        ];
    }

    private function assertCanMutateVault(Grantee $grantee, string $slotKey): void
    {
        $status = $grantee->submission_status ?? 'not_submitted';
        $packageLocked = in_array($status, ['docs_submitted', 'under_review', 'verified', 'resubmission_requested'], true);
        if (! $packageLocked) {
            return;
        }

        $slot = DocumentSubmission::query()
            ->where('grantee_id', $grantee->id)
            ->where('batch_id', $grantee->batch_id)
            ->where('slot_key', $slotKey)
            ->first();

        if ($slot && $slot->status === 'resubmission') {
            return;
        }
        if ($slot && $slot->status === 'draft' && $status === 'resubmission_requested') {
            return;
        }

        throw ValidationException::withMessages([
            'submission' => 'Requirements already submitted. Wait for staff to request a resubmission.',
        ]);
    }

    private function requireSlot(string $studentId, int $batchId, string $slotKey): DocumentSubmission
    {
        $submission = DocumentSubmission::query()
            ->where('student_id', $studentId)
            ->where('batch_id', $batchId)
            ->where('slot_key', $slotKey)
            ->first();

        if (! $submission) {
            throw ValidationException::withMessages([
                'slot_key' => "Complete the {$this->slotLabel($slotKey)} slot before continuing.",
            ]);
        }

        return $submission;
    }

    private function slotLabel(string $slotKey): string
    {
        return match ($slotKey) {
            'course_history' => 'Course History',
            'grade_slip' => 'Grade Slip',
            'specimen_signatures' => '3 Specimen Signatures',
            self::SCHOOL_ID_SLOT => 'School ID',
            default => 'required',
        };
    }
}
