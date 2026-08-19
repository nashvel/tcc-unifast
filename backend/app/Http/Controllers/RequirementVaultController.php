<?php

namespace App\Http\Controllers;

use App\Models\DocumentSubmission;
use App\Models\Grantee;
use App\Models\GranteeIdentityProfile;
use App\Models\User;
use App\Services\BatchWindowService;
use App\Services\IdCardOcrService;
use App\Services\MasterlistTruthService;
use App\Services\RequirementVault\ConfirmRequirementPackageService;
use App\Services\RequirementVault\RequirementVaultPresenter;
use App\Services\RequirementVault\ResubmitRequirementSlotService;
use App\Services\RequirementVault\StoreVaultDocumentSlotService;
use App\Services\RequirementVault\StoreVaultIdentityCheckService;
use App\Services\RequirementVault\StoreVaultSchoolIdService;
use App\Services\RequirementVault\ValidateVaultFrontIdOcrService;
use App\Services\TccRegistrarQrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RequirementVaultController extends Controller
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

    public function __construct(
        private readonly RequirementVaultPresenter $presenter,
    ) {}

    public function show(Request $request, BatchWindowService $windows): JsonResponse
    {
        $context = $this->studentContext($request, $windows, false);

        return response()->json($this->presenter->show($context['window'], $context['grantee']));
    }

    public function validateFrontIdOcr(
        Request $request,
        BatchWindowService $windows,
        ValidateVaultFrontIdOcrService $validateFrontIdOcr,
    ): JsonResponse {
        $context = $this->studentContext($request, $windows);
        $grantee = $context['grantee'];
        $this->assertCanMutateVault($grantee, self::SCHOOL_ID_SLOT);
        $validateFrontIdOcr->assertIdentityComplete($grantee);

        $validated = $request->validate([
            'id_frame' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        return response()->json([
            'data' => $validateFrontIdOcr->validate($request->user(), $grantee, $validated['id_frame']),
        ]);
    }

    public function storeId(
        Request $request,
        BatchWindowService $windows,
        IdCardOcrService $ocr,
        TccRegistrarQrService $qr,
        MasterlistTruthService $truth,
        StoreVaultSchoolIdService $storeSchoolId,
    ): JsonResponse {
        $context = $this->studentContext($request, $windows);
        $grantee = $context['grantee'];
        $this->assertCanMutateVault($grantee, self::SCHOOL_ID_SLOT);
        $batchId = (int) $context['window']['batch']['id'];
        $identity = GranteeIdentityProfile::query()->where('grantee_id', $grantee->id)->first();

        if (! $identity?->isComplete()) {
            throw ValidationException::withMessages([
                'onboarding' => 'Complete identity onboarding (ID scan + liveness) before submitting requirements.',
            ]);
        }

        $validated = $request->validate([
            'id_frame' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'id_face_crop' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'id_back' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            // QR is best-effort for Slot 1 — store flags for staff; never hard-block submit.
            'qr_payload' => ['nullable', 'string', 'max:2000'],
            'face_descriptor' => ['required', 'array', 'size:128'],
            'face_descriptor.*' => ['required', 'numeric'],
            'face_quality_score' => ['required', 'numeric', 'min:0', 'max:1'],
            // Client-reported distances are ignored; kept optional for older clients.
            'distance_vs_reference' => ['nullable', 'numeric', 'min:0'],
            'distance_vs_onboarding_selfie' => ['nullable', 'numeric', 'min:0'],
            'consent_accepted' => ['accepted'],
            'precheck_accepted' => ['accepted'],
        ]);

        $submission = $storeSchoolId->store(
            $request->user(),
            $grantee,
            $batchId,
            $identity,
            $validated,
            $ocr,
            $qr,
            $truth,
            $request->ip(),
        );

        return response()->json(['data' => $this->presenter->document($submission)]);
    }

    public function storeDocument(
        Request $request,
        BatchWindowService $windows,
        StoreVaultDocumentSlotService $storeDocumentSlot,
    ): JsonResponse {
        $context = $this->studentContext($request, $windows);
        $grantee = $context['grantee'];
        $batchId = (int) $context['window']['batch']['id'];

        $slotKey = (string) $request->input('slot_key');
        $isSpecimen = $slotKey === self::SPECIMEN_SIGNATURES_SLOT;

        $validated = $request->validate([
            'slot_key' => [
                'required',
                Rule::in([self::COURSE_HISTORY_SLOT, self::GRADE_SLIP_SLOT, self::SPECIMEN_SIGNATURES_SLOT]),
            ],
            'file' => [
                'required',
                'file',
                $isSpecimen ? 'mimes:jpg,jpeg,png,webp' : 'mimes:pdf',
                $isSpecimen ? 'max:10240' : 'max:20480',
            ],
        ]);

        $submission = $storeDocumentSlot->store(
            $request->user(),
            $grantee,
            $batchId,
            $validated['slot_key'],
            $validated['file'],
            $request->ip(),
        );

        return response()->json(['data' => $this->presenter->document($submission)]);
    }

    /**
     * Resubmit a single returned slot after the student replaced the file (draft → pending_review).
     */
    public function resubmitSlot(
        Request $request,
        BatchWindowService $windows,
        ResubmitRequirementSlotService $resubmitRequirementSlot,
    ): JsonResponse {
        $context = $this->studentContext($request, $windows);
        $grantee = $context['grantee'];
        $batchId = (int) $context['window']['batch']['id'];

        $validated = $request->validate([
            'slot_key' => [
                'required',
                Rule::in([
                    self::SCHOOL_ID_SLOT,
                    self::COURSE_HISTORY_SLOT,
                    self::GRADE_SLIP_SLOT,
                    self::SPECIMEN_SIGNATURES_SLOT,
                ]),
            ],
        ]);
        $result = $resubmitRequirementSlot->resubmit(
            $request->user(),
            $grantee,
            $batchId,
            $validated['slot_key'],
            $request->ip(),
        );

        return response()->json([
            'data' => $this->presenter->document($result['submission']),
            'grantee' => $this->presenter->grantee($result['grantee']),
            'resubmitted' => true,
        ]);
    }

    /**
     * Optional submission liveness log. Does not promote drafts — confirm() is the submit gate.
     */
    public function storeIdentityCheck(
        Request $request,
        BatchWindowService $windows,
        StoreVaultIdentityCheckService $storeIdentityCheck,
    ): JsonResponse {
        $context = $this->studentContext($request, $windows);
        $grantee = $context['grantee'];
        $this->assertCanMutateVault($grantee);
        $batchId = (int) $context['window']['batch']['id'];
        $studentId = $request->user()->student_id;
        $schoolId = $this->requireSlot($studentId, $batchId, self::SCHOOL_ID_SLOT);

        $validated = $request->validate([
            'challenge_sequence' => ['required', 'array', 'size:3'],
            'challenge_sequence.*' => ['required', Rule::in(['blink', 'turn_left', 'turn_right'])],
            'face_descriptor' => ['required', 'array', 'size:128'],
            'face_descriptor.*' => ['required', 'numeric'],
            // Client match flags/distances are ignored; kept optional for older clients.
            'result' => ['nullable', Rule::in(['match', 'no_match'])],
            'distance' => ['nullable', 'numeric', 'min:0'],
            'distances' => ['nullable', 'array'],
            'distances.vs_submission_id' => ['nullable', 'numeric', 'min:0'],
            'distances.vs_id_reference' => ['nullable', 'numeric', 'min:0'],
            'distances.vs_onboarding_selfie' => ['nullable', 'numeric', 'min:0'],
            'confidence_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'consent_accepted' => ['accepted'],
            'liveness_confirmed' => ['accepted'],
            'selfie' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $check = $storeIdentityCheck->store(
            $request->user(),
            $grantee,
            $batchId,
            $schoolId,
            $validated,
            $request->ip(),
        );

        return response()->json([
            'data' => $this->presenter->identityCheck($check),
            'grantee' => $this->presenter->grantee($grantee->fresh()),
            'submitted' => false,
        ]);
    }

    /**
     * Final submit gate: all slots + Slot 1 face bind + name consistency. No submission liveness required.
     */
    public function confirm(
        Request $request,
        BatchWindowService $windows,
        ConfirmRequirementPackageService $confirmPackage,
    ): JsonResponse {
        $context = $this->studentContext($request, $windows);
        $grantee = $context['grantee'];
        $this->assertCanMutateVault($grantee);
        $batchId = (int) $context['window']['batch']['id'];

        $result = $confirmPackage->confirm($request->user(), $grantee, $batchId, $request->ip());

        return response()->json([
            'grantee' => $this->presenter->grantee($result['grantee']),
            'identity_check' => $result['identity_check']
                ? $this->presenter->identityCheck($result['identity_check'])
                : null,
            'name_consistency' => $result['name_consistency'],
            'submitted' => true,
        ]);
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
                self::SCHOOL_ID_SLOT => 'School ID',
                self::COURSE_HISTORY_SLOT => 'Course History',
                self::GRADE_SLIP_SLOT => 'Grade Slip',
                self::SPECIMEN_SIGNATURES_SLOT => '3 Specimen Signatures',
                default => 'required',
            };

            throw ValidationException::withMessages([
                'slot_key' => "Complete the {$label} slot before continuing.",
            ]);
        }

        return $submission;
    }
}
