<?php

namespace App\Services\Sso;

use App\Models\BatchNotification;
use App\Models\ExternalIdentity;
use App\Models\OidcConnection;
use App\Models\SsoIdentityReview;
use App\Models\User;
use App\Services\AuthTokenService;
use App\Services\MasterlistTruthService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SsoIdentityReviewService
{
    public function restricted(User $user): bool
    {
        return SsoIdentityReview::where('user_id', $user->id)->where('status', 'pending')->where('restricts_access', true)->exists();
    }

    public function open(OidcConnection $connection, User $user, string $subject, array $profile, string $reason, bool $restrict): SsoIdentityReview
    {
        $review = SsoIdentityReview::firstOrCreate(['connection_id' => $connection->id, 'user_id' => $user->id,
            'identity_key' => ExternalIdentity::key($connection->issuer, $subject), 'reason' => $reason, 'status' => 'pending'],
            ['subject' => $subject, 'local_claims' => $user->only(['student_id', 'name', 'email']), 'provider_claims' => $profile, 'restricts_access' => $restrict]);
        if ($review->wasRecentlyCreated) {
            if ($restrict) {
                app(AuthTokenService::class)->revokeAll($user);
            }
            foreach (User::whereIn('role', ['admin', 'developer'])->where('account_status', 'active')->get(['id']) as $admin) {
                BatchNotification::create(['user_id' => $admin->id, 'type' => 'sso_identity_review',
                    'title' => 'SIS identity review required', 'body' => 'Open Integration Settings → SIS SSO to review the pending identity match.']);
            }
            SsoAudit::record('identity_review_opened', ['review_id' => $review->id, 'reason' => $reason]);
        }

        return $review;
    }

    public function decide(SsoIdentityReview $review, User $admin, string $decision, string $notes): void
    {
        DB::transaction(function () use ($review, $admin, $decision, $notes) {
            $connection = OidcConnection::whereKey($review->connection_id)->lockForUpdate()->firstOrFail();
            $review = SsoIdentityReview::whereKey($review->id)->lockForUpdate()->firstOrFail();
            if ($review->status !== 'pending') {
                throw ValidationException::withMessages(['decision' => 'This review has already been decided.']);
            }
            $user = User::whereKey($review->user_id)->lockForUpdate()->firstOrFail();
            $identity = ExternalIdentity::where('connection_id', $connection->id)->where('user_id', $user->id)->first();
            if ($decision === 'approve') {
                $claims = $review->provider_claims;
                if ($review->reason !== 'identity_data_changed' || ! SsoRolloutService::studentAllowed($user)
                    || ! app(MasterlistTruthService::class)->studentIdsMatch($claims['student_id'], $user->student_id)
                    || $review->identity_key !== ExternalIdentity::key($connection->issuer, $review->subject)
                    || User::whereRaw('LOWER(email) = ?', [$claims['email']])->where('id', '!=', $user->id)->exists()
                    || ($identity && $identity->identity_key !== $review->identity_key)
                    || ExternalIdentity::where('identity_key', $review->identity_key)->where('user_id', '!=', $user->id)->exists()) {
                    throw ValidationException::withMessages(['decision' => 'This match cannot be approved. Resolve the conflicting identity and issue a new pilot invitation.']);
                }
                $identity ??= new ExternalIdentity(['connection_id' => $connection->id, 'user_id' => $user->id, 'identity_key' => $review->identity_key,
                    'issuer' => $connection->issuer, 'subject' => $review->subject]);
                // Accept the mapping explicitly; never overwrite the local masterlist.
                $identity->fill(['accepted_claims' => $claims, 'enabled' => true])->save();
            } elseif ($identity && $identity->identity_key === $review->identity_key) {
                $identity->update(['enabled' => false]);
            }
            $review->fill(['status' => $decision === 'approve' ? 'approved' : 'rejected', 'reviewed_by' => $admin->id,
                'reviewed_at' => now(), 'decision_notes' => $notes])->save();
            app(AuthTokenService::class)->revokeAll($user);
            BatchNotification::create(['user_id' => $user->id, 'type' => 'sso_review_decided', 'title' => 'SIS identity review completed',
                'body' => $decision === 'approve' ? 'Your SIS identity mapping was approved. Sign in again to continue.' : 'Your SIS identity mapping was not approved. Your local account details were preserved; contact the administrator for help.']);
            SsoAudit::record('identity_review_decided', ['review_id' => $review->id, 'reason' => $decision]);
        });
    }
}
