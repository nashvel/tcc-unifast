<?php

namespace App\Http\Controllers;

use App\Models\DocumentSubmission;
use App\Models\Grantee;
use App\Models\User;
use App\Services\BatchWindowService;
use App\Services\RequirementVault\ConfirmRequirementPackageService;
use App\Services\RequirementVault\RequirementVaultPresenter;
use App\Services\RequirementVault\ResubmitRequirementSlotService;
use App\Services\RequirementVault\StoreVaultDocumentSlotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RequirementVaultController extends Controller
{
    private const COURSE_HISTORY_SLOT = 'course_history';

    private const GRADE_SLIP_SLOT = 'grade_slip';

    private const SPECIMEN_SIGNATURES_SLOT = 'specimen_signatures';

    /**
     * Identity is verified exactly once, during onboarding (ID scan + liveness).
     * The vault holds document slots only — there is no school_id slot here.
     *
     * @var list<string>
     */
    private const REQUIRED_SLOTS = [
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

    public function storeDocument(
        Request $request,
        BatchWindowService $windows,
        StoreVaultDocumentSlotService $storeVaultDocumentSlot,
    ): JsonResponse {
        $context = $this->studentContext($request, $windows);
        $grantee = $context['grantee'];
        $batchId = (int) $context['window']['batch']['id'];

        $slotKey = (string) $request->input('slot_key');
        $this->assertCanMutateVault($grantee, $slotKey !== '' ? $slotKey : null);
        $isSpecimen = $slotKey === self::SPECIMEN_SIGNATURES_SLOT;

        $validated = $request->validate([
            'slot_key' => ['required', Rule::in(self::REQUIRED_SLOTS)],
            'file' => [
                'required',
                'file',
                $isSpecimen ? 'mimes:jpg,jpeg,png,webp,pdf' : 'mimes:pdf',
                'max:20480',
            ],
        ]);

        // store() returns the DocumentSubmission model directly.
        $submission = $storeVaultDocumentSlot->store(
            $request->user(),
            $grantee,
            $batchId,
            $validated['slot_key'],
            $validated['file'],
            $request->ip(),
        );

        return response()->json(['data' => $this->presenter->document($submission)]);
    }

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

    public function confirm(
        Request $request,
        BatchWindowService $windows,
        ConfirmRequirementPackageService $confirmPackage,
    ): JsonResponse {
        $context = $this->studentContext($request, $windows);
        $grantee = $context['grantee'];
        $this->assertCanMutateVault($grantee);
        $batchId = (int) $context['window']['batch']['id'];

        // Named order matters: an earlier revision passed the PIN in the $ipAddress
        // position, which wrote the plaintext PIN into audit_logs.ip_address.
        $result = $confirmPackage->confirm(
            $request->user(),
            $grantee,
            $batchId,
            $request->ip(),
            $request->input('pin'),
        );

        return response()->json([
            'data' => $this->presenter->show($context['window'], $result['grantee']),
            'grantee' => $this->presenter->grantee($result['grantee']),
            'identity_check' => $result['identity_check'],
            'submitted' => true,
            'message' => 'Requirements confirmed and submitted.',
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

            if ($slot && $slot->status === 'resubmission') {
                return;
            }
            if ($slot && $slot->status === 'draft' && $status === 'resubmission_requested') {
                return;
            }
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
}
