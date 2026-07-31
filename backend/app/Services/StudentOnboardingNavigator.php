<?php

namespace App\Services;

use App\Models\Grantee;
use App\Models\User;

class StudentOnboardingNavigator
{
    /**
     * Resume step after login or deep-link for a student mid-onboarding.
     *
     * @return 'blocked'|'kyc'|'id_scan'|'liveness'|'done'
     */
    public function nextStep(User $user, ?Grantee $grantee = null): string
    {
        $status = $user->account_status;

        if ($status === 'blocked') {
            return 'blocked';
        }

        if (in_array($status, ['unverified', 'pending_kyc'], true)) {
            return 'kyc';
        }

        if ($status === 'pending_identity') {
            $grantee ??= $user->grantee;
            $identity = $grantee?->identityProfile;

            if (! $identity || $identity->status === 'pending_id_scan') {
                return 'id_scan';
            }

            if ($identity->status === 'pending_liveness') {
                return 'liveness';
            }
        }

        return 'done';
    }

    public function frontendPath(string $nextStep): string
    {
        return match ($nextStep) {
            'kyc' => '/student/kyc',
            'id_scan' => '/student/onboarding/id-scan',
            'liveness' => '/student/onboarding/liveness',
            'blocked' => '/locked',
            default => '/student',
        };
    }
}
