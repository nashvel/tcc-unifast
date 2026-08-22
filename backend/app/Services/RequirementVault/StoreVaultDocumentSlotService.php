<?php

namespace App\Services\RequirementVault;

use App\Jobs\ProcessRequirementSubmissionPipeline;
use App\Models\AuditLog;
use App\Models\DocumentSubmission;
use App\Models\Grantee;
use App\Models\User;
use App\Support\SecureUpload;
use App\Support\VaultFileStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class StoreVaultDocumentSlotService
{
    private const SCHOOL_ID_SLOT = 'school_id';

    private const COURSE_HISTORY_SLOT = 'course_history';

    private const GRADE_SLIP_SLOT = 'grade_slip';

    private const SPECIMEN_SIGNATURES_SLOT = 'specimen_signatures';

    /** @var list<string> */
    private const REQUIRED_SLOTS = [
        self::SCHOOL_ID_SLOT,
        self::COURSE_HISTORY_SLOT,
        self::GRADE_SLIP_SLOT,
        self::SPECIMEN_SIGNATURES_SLOT,
    ];

    /** @var list<string> */
    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    /** @var list<string> */
    private const PDF_MIMES = ['application/pdf'];

    public function store(User $user, Grantee $grantee, int $batchId, string $slotKey, UploadedFile $file, ?string $ipAddress): DocumentSubmission
    {
        $this->requireSlot((string) $user->student_id, $batchId, self::SCHOOL_ID_SLOT);
        $this->assertCanMutateVault($grantee, $slotKey);

        $allowed = $slotKey === self::SPECIMEN_SIGNATURES_SLOT ? self::IMAGE_MIMES : self::PDF_MIMES;
        $detectedMime = SecureUpload::assertAllowedMime($file, $allowed, 'file');

        $existing = DocumentSubmission::query()
            ->where('student_id', $user->student_id)
            ->where('batch_id', $batchId)
            ->where('slot_key', $slotKey)
            ->first();
        $previousPath = $existing?->stored_path;

        $slotStatus = (! $existing && $this->packageAlreadySentToStaff($grantee))
            ? 'pending_review'
            : 'draft';

        $path = VaultFileStorage::storeDocument($file, $grantee->id, $batchId);

        $submission = DocumentSubmission::updateOrCreate(
            [
                'student_id' => $user->student_id,
                'batch_id' => $batchId,
                'slot_key' => $slotKey,
            ],
            [
                'grantee_id' => $grantee->id,
                'student_name' => $user->name,
                'document_type' => $this->slotLabel($slotKey),
                'original_name' => SecureUpload::sanitizeOriginalName($file->getClientOriginalName(), $slotKey),
                'stored_path' => $path,
                'mime_type' => $detectedMime,
                'file_size' => $file->getSize(),
                'status' => $slotStatus,
                'risk_level' => 'low',
                'extracted_text' => null,
                'ocr_payload' => null,
                'metadata_payload' => null,
            ],
        );

        if (is_string($previousPath) && $previousPath !== '' && $previousPath !== $path) {
            VaultFileStorage::deleteIfOwned($previousPath);
        }

        if ($slotStatus === 'pending_review') {
            ProcessRequirementSubmissionPipeline::dispatch($grantee->id, $batchId, false);
        }

        AuditLog::create([
            'actor' => $user->name,
            'role' => 'Student',
            'action' => 'requirement_uploaded',
            'module' => 'Requirements Submission',
            'target' => "Submission #{$submission->id}",
            'context' => [
                'slot' => $slotKey,
                'status' => $slotStatus,
            ],
            'ip_address' => $ipAddress,
        ]);

        return $submission->fresh();
    }

    private function assertCanMutateVault(Grantee $grantee, ?string $slotKey = null): void
    {
        $status = $grantee->submission_status ?? 'not_submitted';
        $packageLocked = in_array($status, ['docs_submitted', 'under_review', 'verified', 'resubmission_requested'], true);
        if (! $packageLocked) {
            return;
        }

        if ($slotKey) {
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
            if (! $slot && $this->packageAlreadySentToStaff($grantee) && ! $this->packageHasAllRequiredSlots($grantee)) {
                return;
            }
        }

        throw ValidationException::withMessages([
            'submission' => 'Requirements already submitted. Wait for staff to request a resubmission.',
        ]);
    }

    private function packageAlreadySentToStaff(Grantee $grantee): bool
    {
        return in_array(
            $grantee->submission_status ?? 'not_submitted',
            ['docs_submitted', 'under_review', 'verified', 'resubmission_requested'],
            true,
        );
    }

    private function packageHasAllRequiredSlots(Grantee $grantee): bool
    {
        $count = DocumentSubmission::query()
            ->where('grantee_id', $grantee->id)
            ->where('batch_id', $grantee->batch_id)
            ->whereIn('slot_key', self::REQUIRED_SLOTS)
            ->distinct()
            ->count('slot_key');

        return $count >= count(self::REQUIRED_SLOTS);
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
                'slot_key' => 'Complete the School ID slot before continuing.',
            ]);
        }

        return $submission;
    }

    private function slotLabel(string $slotKey): string
    {
        return match ($slotKey) {
            self::COURSE_HISTORY_SLOT => 'Course History',
            self::GRADE_SLIP_SLOT => 'Grade Slip',
            self::SPECIMEN_SIGNATURES_SLOT => '3 Specimen Signatures',
            default => 'Requirement',
        };
    }
}
