<?php

namespace App\Services;

use App\Models\Grantee;
use App\Models\User;

class StudentOnboardingNavigator
{
    /**
     * Resume step after login or deep-link for a student mid-onboarding.
     *
     * @return 'blocked'|'kyc'|'id_scan'|'liveness'|'face_review'|'done'
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

        if ($status === 'pending_face_review') {
            return 'face_review';
        }

        if ($status === 'pending_identity') {
            $grantee ??= $user->grantee;
            $identity = $grantee?->identityProfile;

            if (! $identity || $identity->status === 'pending_id_scan') {
                return 'id_scan';
            }

            if ($identity->status === 'pending_face_review') {
                return 'face_review';
            }

            if ($identity->status === 'pending_liveness') {
                return 'liveness';
            }

            // Stale "completed" profile without finishing onboarding (e.g. re-seed left old row).
            if ($identity->status === 'completed' && $identity->onboarding_completed_at === null) {
                return $identity->id_scan_completed_at ? 'liveness' : 'id_scan';
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
            'face_review' => '/student/onboarding/pending-review',
            'blocked' => '/locked',
            default => '/student',
        };
    }
}
