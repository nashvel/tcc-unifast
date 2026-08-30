<?php

namespace App\Services\RequirementVault;

use App\Jobs\ProcessRequirementSubmissionPipeline;
use App\Models\AuditLog;
use App\Models\DocumentSubmission;
use App\Models\Grantee;
use App\Models\GranteeIdentityProfile;
use App\Models\RequirementIdentityCheck;
use App\Models\User;
use App\Support\FaceDescriptorMath;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConfirmRequirementPackageService
{
    private const COURSE_HISTORY_SLOT = 'course_history';

    private const GRADE_SLIP_SLOT = 'grade_slip';

    private const SPECIMEN_SIGNATURES_SLOT = 'specimen_signatures';

    /**
     * Identity is verified once during onboarding; the vault has no school_id slot.
     *
     * @var list<string>
     */
    private const REQUIRED_SLOTS = [
        self::COURSE_HISTORY_SLOT,
        self::GRADE_SLIP_SLOT,
        self::SPECIMEN_SIGNATURES_SLOT,
    ];

    /**
     * @return array{grantee: Grantee, identity_check: ?RequirementIdentityCheck, name_consistency: array<string, mixed>}
     */
    public function confirm(
        User $user,
        Grantee $grantee,
        int $batchId,
        ?string $ipAddress,
        ?string $pin = null,
    ): array {
        // The vault PIN was previously prompted for by the UI but never checked
        // server-side, so a direct API call could confirm without it.
        $this->assertSecurityPin($user, $pin, $ipAddress);

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

        $missing = $this->missingRequiredSlotLabels((string) $user->student_id, $batchId);
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'submission' => 'Submit all three documents to staff before confirming. Missing: '
                    .implode(', ', $missing).'.',
            ]);
        }

        // Identity was already proven during onboarding (ID scan + liveness); the
        // vault only re-checks that submitted names stay consistent.
        $this->assertIdentityOnboardingComplete($grantee);
        $nameFlags = $this->assertNameConsistency($user, $grantee, $batchId);

        $check = RequirementIdentityCheck::query()
            ->where('grantee_id', $grantee->id)
            ->where('batch_id', $batchId)
            ->where('user_id', $user->id)
            ->latest('checked_at')
            ->first();

        $identityFailed = (bool) ($check?->manual_review_required)
            || ($nameFlags['gradeslip_mismatch'] ?? false);

        $grantee = $this->promoteDraftsAndQueuePipeline($user, $grantee, $batchId, $check, $identityFailed, $ipAddress);

        return [
            'grantee' => $grantee,
            'identity_check' => $check,
            'name_consistency' => $nameFlags,
        ];
    }

    /**
     * Verify the 6-digit Document Vault PIN when the student has configured one.
     *
     * Opt-in by design: students without a PIN are unaffected. Throttled per user
     * because a 6-digit PIN is only 10^6 combinations — without a limiter it would
     * be trivially brute-forceable over the API.
     */
    private function assertSecurityPin(User $user, ?string $pin, ?string $ipAddress): void
    {
        $hash = (string) ($user->security_pin ?? '');
        if ($hash === '') {
            return;
        }

        $pin = trim((string) $pin);
        if ($pin === '') {
            throw ValidationException::withMessages([
                'pin' => 'Enter your 6-digit Document Vault PIN to submit.',
            ]);
        }

        $key = 'vault-pin:'.$user->id;
        $maxAttempts = max(1, (int) config('services.requirement_vault.pin_max_attempts', 5));

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            throw ValidationException::withMessages([
                'pin' => 'Too many incorrect PIN attempts. Try again in '
                    .RateLimiter::availableIn($key).' seconds.',
            ]);
        }

        if (! Hash::check($pin, $hash)) {
            RateLimiter::hit($key, 900);

            AuditLog::create([
                'actor' => $user->name,
                'role' => 'Student',
                'action' => 'vault_pin_rejected',
                'module' => 'Requirements Submission',
                'target' => "User #{$user->id}",
                'context' => ['attempts_remaining' => RateLimiter::remaining($key, $maxAttempts)],
                'ip_address' => $ipAddress,
            ]);

            throw ValidationException::withMessages([
                'pin' => 'That PIN is incorrect.',
            ]);
        }

        RateLimiter::clear($key);
    }

    /**
     * The vault is gated on onboarding identity verification, not on a re-scan.
     */
    private function assertIdentityOnboardingComplete(Grantee $grantee): void
    {
        $identity = GranteeIdentityProfile::query()->where('grantee_id', $grantee->id)->first();

        if (! $identity?->isComplete()) {
            throw ValidationException::withMessages([
                'identity' => 'Complete identity onboarding (ID scan + liveness) before submitting requirements.',
            ]);
        }
    }

    /**
     * Require profile ~= masterlist/grantee name, cross-checked against the ID OCR
     * captured during onboarding.
     * Grade slip name is best-effort: soft-flag when OCR text already exists; never block if missing.
     *
     * @return array{profile: string, grantee: string, school_id: ?string, grade_slip: ?string, gradeslip_mismatch: bool}
     */
    private function assertNameConsistency(
        User $user,
        Grantee $grantee,
        int $batchId,
    ): array {
        $profileName = trim((string) $user->name);
        $granteeName = trim((string) $grantee->full_name);

        if ($profileName === '' || $granteeName === '') {
            throw ValidationException::withMessages([
                'name_match' => 'Profile and masterlist names are required before submit.',
            ]);
        }

        if (! $this->namesLooselyMatch($profileName, $granteeName)) {
            throw ValidationException::withMessages([
                'name_match' => 'Your account name does not match the CHED masterlist / grantee name.',
            ]);
        }

        // Name captured by OCR from the school ID during identity onboarding.
        $identity = GranteeIdentityProfile::query()->where('grantee_id', $grantee->id)->first();
        $schoolIdName = data_get($identity?->id_ocr_payload, 'extracted_name');
        $schoolIdName = is_string($schoolIdName) ? trim($schoolIdName) : null;
        if ($schoolIdName === '') {
            $schoolIdName = null;
        }

        if ($schoolIdName !== null && ! $this->namesLooselyMatch($profileName, $schoolIdName)) {
            throw ValidationException::withMessages([
                'name_match' => 'The name on your scanned school ID does not match your profile / masterlist name. Contact the scholarship office.',
            ]);
        }

        $gradeSlip = DocumentSubmission::query()
            ->where('student_id', $user->student_id)
            ->where('batch_id', $batchId)
            ->where('slot_key', self::GRADE_SLIP_SLOT)
            ->first();

        [$gradeSlipName, $gradeslipMismatch] = $this->gradeSlipNameCheck($gradeSlip, $profileName);
        if ($gradeslipMismatch) {
            $gradeSlip?->update([
                'identity_review_required' => true,
                'identity_review_reason' => 'Grade slip name (from existing OCR/text) does not match profile / masterlist.',
                'risk_level' => 'medium',
            ]);
        }

        return [
            'profile' => $profileName,
            'grantee' => $granteeName,
            'school_id' => $schoolIdName,
            'grade_slip' => $gradeSlipName,
            'gradeslip_mismatch' => $gradeslipMismatch,
        ];
    }

    /**
     * Soft check only when draft already has OCR/extracted text. Missing text does not block submit.
     *
     * @return array{0: ?string, 1: bool}
     */
    private function gradeSlipNameCheck(?DocumentSubmission $doc, string $profileName): array
    {
        if (! $doc) {
            return [null, false];
        }

        $explicit = data_get($doc->metadata_payload, 'ocr.extracted_name')
            ?: data_get($doc->ocr_payload, 'extracted_name')
            ?: data_get($doc->ocr_payload, 'result.extracted_name');
        if (is_string($explicit) && trim($explicit) !== '') {
            $name = trim($explicit);

            return [$name, ! $this->namesLooselyMatch($profileName, $name)];
        }

        $text = trim((string) ($doc->extracted_text ?? ''));
        if ($text === '') {
            $text = trim((string) data_get($doc->ocr_payload, 'result.combined_text', ''));
        }
        if ($text === '') {
            return [null, false];
        }

        $haystack = $this->nameKey($text);
        $expected = $this->nameKey($profileName);
        if ($expected !== '' && str_contains($haystack, $expected)) {
            return [$profileName, false];
        }

        $parts = array_values(array_filter(explode(' ', $expected), fn (string $p) => strlen($p) > 1));
        if ($parts === []) {
            return [null, false];
        }
        $hits = count(array_filter($parts, fn (string $p) => str_contains($haystack, $p)));
        $ok = $hits >= max(2, (int) floor(count($parts) * 0.6));

        return [null, ! $ok];
    }

    private function promoteDraftsAndQueuePipeline(
        User $user,
        Grantee $grantee,
        int $batchId,
        ?RequirementIdentityCheck $check,
        bool $identityFailed,
        ?string $ipAddress,
    ): Grantee {
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
            'actor' => $user->name,
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
            'ip_address' => $ipAddress,
        ]);

        return $grantee->fresh();
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
                    self::SPECIMEN_SIGNATURES_SLOT => 'ID (Back-to-Back) & Specimen',
                    default => $slotKey,
                };
            }
        }

        return $missing;
    }

    private function namesLooselyMatch(string $left, string $right): bool
    {
        $expected = $this->nameKey($left);
        $candidate = $this->nameKey($right);
        if ($expected === '' || $candidate === '') {
            return false;
        }
        if ($expected === $candidate) {
            return true;
        }

        $expectedParts = array_values(array_filter(explode(' ', $expected)));
        $candidateParts = array_values(array_filter(explode(' ', $candidate)));
        if (count($expectedParts) < 2 || count($candidateParts) < 2) {
            return $expected === $candidate;
        }

        $overlap = count(array_intersect($expectedParts, $candidateParts));

        return $overlap >= max(2, (int) floor(count($expectedParts) * 0.6));
    }

    private function nameKey(string $value): string
    {
        return Str::of($value)->lower()->replaceMatches('/\s+/', ' ')->trim()->toString();
    }
}
