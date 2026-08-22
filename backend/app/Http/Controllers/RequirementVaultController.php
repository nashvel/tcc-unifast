<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessRequirementSubmissionPipeline;
use App\Models\AuditLog;
use App\Models\DocumentSubmission;
use App\Models\Grantee;
use App\Models\GranteeIdentityProfile;
use App\Models\RequirementIdentityCheck;
use App\Models\User;
use App\Services\BatchWindowService;
use App\Support\SecureUpload;
use App\Support\VaultFileStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RequirementVaultController extends Controller
{
    private const COURSE_HISTORY_SLOT = 'course_history';

    private const GRADE_SLIP_SLOT = 'grade_slip';

    private const SPECIMEN_SIGNATURES_SLOT = 'specimen_signatures';

    /** @var list<string> */
    private const REQUIRED_SLOTS = [
        self::COURSE_HISTORY_SLOT,
        self::GRADE_SLIP_SLOT,
        self::SPECIMEN_SIGNATURES_SLOT,
    ];

    /** @var list<string> */
    private const IMAGE_OR_PDF_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

    /** @var list<string> */
    private const PDF_MIMES = ['application/pdf'];

    public function show(Request $request, BatchWindowService $windows): JsonResponse
    {
        $context = $this->studentContext($request, $windows, false);
        $identity = $context['grantee']
            ? GranteeIdentityProfile::query()->where('grantee_id', $context['grantee']->id)->first()
            : null;

        return response()->json([
            'window' => $context['window'],
            'grantee' => $context['grantee'] ? $this->presentGrantee($context['grantee']) : null,
            'slots' => $context['grantee'] ? $this->presentSlots($context['grantee']) : [],
            'identity_check' => $context['grantee'] ? $this->latestIdentityCheck($context['grantee']) : null,
            'onboarding_refs' => ($identity && $context['grantee']) ? [
                'id_reference_face_url' => $identity->id_reference_face_path
                    ? VaultFileStorage::authIdentityUrl('id_reference_face.jpg')
                    : null,
                'id_onboarding_frame_url' => is_string(data_get($identity->id_ocr_payload, 'frame_path'))
                    && data_get($identity->id_ocr_payload, 'frame_path') !== ''
                    ? VaultFileStorage::authIdentityUrl('id_onboarding_frame.jpg')
                    : null,
                'onboarding_selfie_url' => $identity->onboarding_selfie_path
                    ? VaultFileStorage::authIdentityUrl('onboarding_selfie.jpg')
                    : null,
                'completed' => $identity->isComplete(),
            ] : null,
        ]);
    }

    public function storeDocument(Request $request, BatchWindowService $windows): JsonResponse
    {
        $context = $this->studentContext($request, $windows);
        $grantee = $context['grantee'];
        $batchId = (int) $context['window']['batch']['id'];

        $slotKey = (string) $request->input('slot_key');
        $this->assertCanMutateVault($grantee, $slotKey !== '' ? $slotKey : null);
        $isSpecimen = $slotKey === self::SPECIMEN_SIGNATURES_SLOT;

        $validated = $request->validate([
            'slot_key' => [
                'required',
                Rule::in([self::COURSE_HISTORY_SLOT, self::GRADE_SLIP_SLOT, self::SPECIMEN_SIGNATURES_SLOT]),
            ],
            'file' => [
                'required',
                'file',
                $isSpecimen ? 'mimes:jpg,jpeg,png,webp,pdf' : 'mimes:pdf',
                'max:20480',
            ],
        ]);

        $file = $validated['file'];
        $slotKey = $validated['slot_key'];
        $allowed = $slotKey === self::SPECIMEN_SIGNATURES_SLOT ? self::IMAGE_OR_PDF_MIMES : self::PDF_MIMES;
        $detectedMime = SecureUpload::assertAllowedMime($file, $allowed, 'file');

        $existing = DocumentSubmission::query()
            ->where('student_id', $request->user()->student_id)
            ->where('batch_id', $batchId)
            ->where('slot_key', $slotKey)
            ->first();
        $previousPath = $existing?->stored_path;
        // First-time drafts stay private until confirm. Legacy never-submitted slots on an
        // already-sent package go straight to staff without unlocking other pending slots.
        $slotStatus = (! $existing && $this->packageAlreadySentToStaff($grantee))
            ? 'pending_review'
            : 'draft';

        $path = VaultFileStorage::storeDocument($file, $grantee->id, $batchId);
        $label = match ($slotKey) {
            self::COURSE_HISTORY_SLOT => 'Course History',
            self::GRADE_SLIP_SLOT => 'Grade Slip',
            self::SPECIMEN_SIGNATURES_SLOT => 'ID Back-to-Back with Specimen',
            default => 'Requirement',
        };

        $submission = DocumentSubmission::updateOrCreate(
            [
                'student_id' => $request->user()->student_id,
                'batch_id' => $batchId,
                'slot_key' => $slotKey,
            ],
            [
                'grantee_id' => $grantee->id,
                'student_name' => $request->user()->name,
                'document_type' => $label,
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

        $this->audit($request, 'requirement_uploaded', $submission, [
            'slot' => $slotKey,
            'status' => $slotStatus,
        ]);

        return response()->json(['data' => $this->presentVaultDocument($submission->fresh())]);
    }

    /**
     * Resubmit a single returned slot after the student replaced the file (draft → pending_review).
     */
    public function resubmitSlot(Request $request, BatchWindowService $windows): JsonResponse
    {
        $context = $this->studentContext($request, $windows);
        $grantee = $context['grantee'];
        $batchId = (int) $context['window']['batch']['id'];
        $studentId = $request->user()->student_id;

        $validated = $request->validate([
            'slot_key' => [
                'required',
                Rule::in([
                    self::COURSE_HISTORY_SLOT,
                    self::GRADE_SLIP_SLOT,
                    self::SPECIMEN_SIGNATURES_SLOT,
                ]),
            ],
        ]);
        $slotKey = $validated['slot_key'];

        $this->assertCanMutateVault($grantee, $slotKey);

        $submission = $this->requireSlot($studentId, $batchId, $slotKey);
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
            'actor' => $request->user()->name,
            'role' => 'Student',
            'action' => 'requirement_slot_resubmitted',
            'module' => 'Requirements Submission',
            'target' => "Submission #{$submission->id}",
            'context' => [
                'batch_id' => $batchId,
                'slot_key' => $slotKey,
                'pipeline' => 'queued',
            ],
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'data' => $this->presentVaultDocument($submission->fresh()),
            'grantee' => $this->presentGrantee($grantee->fresh()),
            'resubmitted' => true,
        ]);
    }

    /**
     * Optional submission liveness log. Does not promote drafts — confirm() is the submit gate.
     */

    /**
     * Final submit gate: all slots + Slot 1 face bind + name consistency. No submission liveness required.
     */
    public function confirm(Request $request, BatchWindowService $windows): JsonResponse
    {
        $context = $this->studentContext($request, $windows);
        $grantee = $context['grantee'];
        $this->assertCanMutateVault($grantee);
        $batchId = (int) $context['window']['batch']['id'];
        $studentId = $request->user()->student_id;

        $status = $grantee->submission_status ?? 'not_submitted';
        if (in_array($status, ['docs_submitted', 'under_review', 'verified'], true)) {
            throw ValidationException::withMessages([
                'submission' => 'Requirements were already confirmed for this batch.',
            ]);
        }
        if ($status === 'resubmission_requested') {
            throw ValidationException::withMessages([
                'submission' => 'Staff requested a resubmission. Resubmit only returned document(s) — use Replace then Resubmit on each returned slot.',
            ]);
        }

        $missing = $this->missingRequiredSlotLabels($studentId, $batchId);
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'submission' => 'Submit all required documents to staff before confirming. Missing: '
                    .implode(', ', $missing).'.',
            ]);
        }

        $user = $request->user();
        if (! empty($user->security_pin)) {
            $request->validate([
                'pin' => ['required', 'string'],
            ], [
                'pin.required' => 'A Security PIN is required to confirm your submission.',
            ]);

            if (! Hash::check($request->input('pin'), $user->security_pin)) {
                throw ValidationException::withMessages([
                    'pin' => 'The provided Security PIN is incorrect.',
                ]);
            }
        }

        $identityFailed = false;

        $grantee = $this->promoteDraftsAndQueuePipeline($request, $grantee, $batchId, null, $identityFailed);

        return response()->json([
            'grantee' => $this->presentGrantee($grantee),
            'submitted' => true,
        ]);
    }

    private function promoteDraftsAndQueuePipeline(
        Request $request,
        Grantee $grantee,
        int $batchId,
        ?RequirementIdentityCheck $check,
        bool $identityFailed = false,
    ): Grantee {
        // Promote student drafts into the staff validation queue, then run OCR/metadata.
        DocumentSubmission::query()
            ->where('grantee_id', $grantee->id)
            ->where('batch_id', $batchId)
            ->whereIn('status', ['draft', 'resubmission'])
            ->update(['status' => 'pending_review']);

        $grantee->update([
            'submission_status' => 'docs_submitted',
            'submitted_at' => now(),
        ]);

        ProcessRequirementSubmissionPipeline::dispatch(
            $grantee->id,
            $batchId,
            $identityFailed,
        );

        AuditLog::create([
            'actor' => $request->user()->name,
            'role' => 'Student',
            'action' => 'requirements_confirmed',
            'module' => 'Requirements Submission',
            'target' => "Grantee #{$grantee->id}",
            'context' => [
                'batch_id' => $batchId,
                'identity_result' => $check?->result,
                'pipeline' => 'queued',
                'via' => 'confirm',
                'identity_failed' => $identityFailed,
            ],
            'ip_address' => $request->ip(),
        ]);

        return $grantee->fresh();
    }

    /**
     * Require profile ≈ masterlist/grantee ≈ school ID OCR name.
     * Grade slip name is best-effort: soft-flag when OCR text already exists; never block if missing.
     *
     * @return array{profile: string, grantee: string, school_id: ?string, grade_slip: ?string, gradeslip_mismatch: bool}
     */

    /**
     * Soft check only when draft already has OCR/extracted text. Missing text does not block submit.
     *
     * @return array{0: ?string, 1: bool}
     */
    private function nameKey(string $value): string
    {
        return Str::of($value)->lower()->replaceMatches('/\s+/', ' ')->trim()->toString();
    }

    private function studentContext(Request $request, BatchWindowService $windows, bool $requireOpen = true): array
    {
        $user = $request->user();
        if ($user->account_status !== 'active') {
            throw ValidationException::withMessages([
                'account_status' => 'Complete KYC and identity onboarding before accessing the Requirement Vault.',
            ]);
        }

        $window = $windows->windowForStudent($user);
        if ($requireOpen && ! $window['open']) {
            throw ValidationException::withMessages(['submission_window' => $window['message']]);
        }

        $grantee = $this->ownedGrantee($user);

        if ($requireOpen && ! $grantee) {
            throw ValidationException::withMessages(['grantee' => 'Your grantee profile is not assigned.']);
        }

        return ['window' => $window, 'grantee' => $grantee];
    }

    private function ownedGrantee(User $user): ?Grantee
    {
        $byUser = Grantee::query()->where('user_id', $user->id)->first();
        if ($byUser) {
            return $byUser;
        }

        if (! $user->student_id) {
            return null;
        }

        // Fallback only for unlinked masterlist rows for this student — never another user's grantee.
        return Grantee::query()
            ->where('student_id', $user->student_id)
            ->whereNull('user_id')
            ->first();
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

            // Returned slots, or drafts created by replacing a returned slot.
            if ($slot && $slot->status === 'resubmission') {
                return;
            }
            if ($slot && $slot->status === 'draft' && $status === 'resubmission_requested') {
                return;
            }
            // Legacy incomplete packages: allow filling never-submitted slots only.
            if (! $slot && $this->packageAlreadySentToStaff($grantee) && ! $this->packageHasAllRequiredSlots($grantee)) {
                return;
            }
        } else {
            $hasOpenResubmission = DocumentSubmission::query()
                ->where('grantee_id', $grantee->id)
                ->where('batch_id', $grantee->batch_id)
                ->whereIn('status', ['resubmission', 'draft'])
                ->exists();

            if ($status === 'resubmission_requested' && $hasOpenResubmission) {
                return;
            }
        }

        throw ValidationException::withMessages([
            'submission' => 'Requirements already submitted. Wait for staff to request a resubmission.',
        ]);
    }

    /**
     * @return list<string>
     */
    private function missingRequiredSlotLabels(string $studentId, int $batchId): array
    {
        $present = DocumentSubmission::query()
            ->where('student_id', $studentId)
            ->where('batch_id', $batchId)
            ->whereIn('slot_key', self::REQUIRED_SLOTS)
            ->pluck('slot_key')
            ->all();

        $missing = [];
        foreach (self::REQUIRED_SLOTS as $slotKey) {
            if (! in_array($slotKey, $present, true)) {
                $missing[] = match ($slotKey) {
                    self::COURSE_HISTORY_SLOT => 'Course History',
                    self::GRADE_SLIP_SLOT => 'Grade Slip',
                    self::SPECIMEN_SIGNATURES_SLOT => 'ID Back-to-Back with Specimen',
                    default => $slotKey,
                };
            }
        }

        return $missing;
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
            $label = match ($slotKey) {
                self::COURSE_HISTORY_SLOT => 'Course History',
                self::GRADE_SLIP_SLOT => 'Grade Slip',
                self::SPECIMEN_SIGNATURES_SLOT => 'ID Back-to-Back with Specimen',
                default => 'required',
            };

            throw ValidationException::withMessages([
                'slot_key' => "Complete the {$label} slot before continuing.",
            ]);
        }

        return $submission;
    }

    private function presentSlots(Grantee $grantee): array
    {
        $rows = DocumentSubmission::query()
            ->where('batch_id', $grantee->batch_id)
            ->where(function ($query) use ($grantee): void {
                $query->where('grantee_id', $grantee->id);
                if ($grantee->student_id) {
                    $query->orWhere('student_id', $grantee->student_id);
                }
            })
            ->orderBy('id')
            ->get();

        $slots = [];
        foreach ($rows as $item) {
            $slotKey = (string) $item->slot_key;
            if ($slotKey === '') {
                continue;
            }

            try {
                $slots[$slotKey] = $this->presentVaultDocument($item);
            } catch (\Throwable $exception) {
                report($exception);
                // Keep the slot visible even if encrypted face payload cannot be decoded.
                $slots[$slotKey] = [
                    'id' => $item->id,
                    'slot_key' => $slotKey,
                    'document_type' => $item->document_type,
                    'original_name' => $item->original_name,
                    'secondary_original_name' => $item->secondary_original_name,
                    'status' => $item->status,
                    'risk_level' => $item->risk_level,
                    'face_quality_score' => $item->face_quality_score,
                    'identity_review_required' => (bool) $item->identity_review_required,
                    'identity_review_reason' => $item->identity_review_reason,
                    'review_notes' => $item->review_notes,
                    'created_at' => $item->created_at,
                    'file_url' => VaultFileStorage::authStudentSubmissionUrl($item, 'primary'),
                    'secondary_file_url' => VaultFileStorage::authStudentSubmissionUrl($item, 'secondary'),
                    'face_descriptor' => null,
                ];
            }
        }

        return $slots;
    }

    private function latestIdentityCheck(Grantee $grantee): ?array
    {
        $check = RequirementIdentityCheck::query()
            ->where('grantee_id', $grantee->id)
            ->where('batch_id', $grantee->batch_id)
            ->latest('checked_at')
            ->first();

        return $check ? $this->presentIdentityCheck($check) : null;
    }

    private function presentGrantee(Grantee $grantee): array
    {
        return [
            'id' => $grantee->id,
            'student_id' => $grantee->student_id,
            'full_name' => $grantee->full_name,
            'submission_status' => $grantee->submission_status ?? 'not_submitted',
            'submitted_at' => $grantee->submitted_at,
        ];
    }

    private function presentVaultDocument(DocumentSubmission $item): array
    {
        return [
            'id' => $item->id,
            'slot_key' => $item->slot_key,
            'document_type' => $item->document_type,
            'original_name' => $item->original_name,
            'secondary_original_name' => $item->secondary_original_name,
            'status' => $item->status,
            'risk_level' => $item->risk_level,
            'face_quality_score' => $item->face_quality_score,
            'identity_review_required' => $item->identity_review_required,
            'identity_review_reason' => $item->identity_review_reason,
            'review_notes' => $item->review_notes,
            'created_at' => $item->created_at,
            'file_url' => VaultFileStorage::authStudentSubmissionUrl($item, 'primary'),
            'secondary_file_url' => VaultFileStorage::authStudentSubmissionUrl($item, 'secondary'),
        ];
    }

    private function presentIdentityCheck(RequirementIdentityCheck $check): array
    {
        $selfieFilename = $check->selfie_path ? basename((string) $check->selfie_path) : null;

        return [
            'id' => $check->id,
            'challenge_sequence' => $check->challenge_sequence,
            'result' => $check->result,
            'distance' => $check->distance,
            'distances' => $check->distances,
            'selfie_url' => $selfieFilename
                ? VaultFileStorage::authIdentityUrl($selfieFilename)
                : null,
            'liveness_confirmed' => (bool) $check->liveness_confirmed,
            'confidence_score' => $check->confidence_score,
            'manual_review_required' => $check->manual_review_required,
            'consent_accepted_at' => $check->consent_accepted_at,
            'checked_at' => $check->checked_at,
        ];
    }

    private function audit(Request $request, string $action, DocumentSubmission $submission, array $context): void
    {
        AuditLog::create([
            'actor' => $request->user()->name,
            'role' => 'Student',
            'action' => $action,
            'module' => 'Requirements Submission',
            'target' => "Submission #{$submission->id}",
            'context' => $context,
            'ip_address' => $request->ip(),
        ]);
    }

    /**
     * @return array{ocr: array<string, mixed>, match: array<string, mixed>}
     */
}
