<?php

namespace App\Services\Sso;

use App\Models\ExternalIdentity;
use App\Models\OidcConnection;
use App\Models\SsoIdentityReview;
use App\Models\User;
use App\Services\MasterlistTruthService;
use Illuminate\Support\Facades\DB;

class SsoIdentityMatcher
{
    public function __construct(private MasterlistTruthService $truth, private SsoIdentityReviewService $reviews) {}

    public function match(OidcConnection $connection, array $claims, ?int $pilotUser = null): array
    {
        return DB::transaction(function () use ($connection, $claims, $pilotUser) {
            OidcConnection::whereKey($connection->id)->lockForUpdate()->firstOrFail();
            $profile = $this->profile($connection, $claims);
            $key = ExternalIdentity::key($connection->issuer, $claims['sub']);
            $binding = ExternalIdentity::where('identity_key', $key)->first();
            $blocked = fn ($code) => ['user' => null, 'code' => $code, 'under_verification' => false];
            if ($binding && ! $binding->enabled) {
                return $blocked('sso_account_blocked');
            }
            if (SsoIdentityReview::where('identity_key', $key)->where('status', 'rejected')->exists()) {
                return $blocked('sso_account_blocked');
            }
            // Use the same exact normalized comparison as the masterlist. Read only
            // IDs/numbers in bounded chunks; never fuzzy-match a person's identity.
            $matches = User::whereNotNull('student_id')->select(['id', 'student_id'])->lazyById(500)
                ->filter(fn ($user) => $this->truth->studentIdsMatch($profile['student_id'], $user->student_id))
                ->take(2)->pluck('id')->all();
            $user = $binding ? User::find($binding->user_id) : (isset($matches[0]) ? User::find($matches[0]) : null);
            if (! $user) {
                return $blocked('sso_not_eligible');
            }
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($pilotUser !== null && $user->id !== $pilotUser) {
                return $blocked('sso_not_eligible');
            }
            if (! SsoRolloutService::studentAllowed($user)) {
                $this->reviews->open($connection, $user, $claims['sub'], $profile, 'account_or_role_blocked', false);

                return $blocked('sso_account_blocked');
            }
            $emailOwners = User::whereRaw('LOWER(email) = ?', [$profile['email']])->pluck('id');
            $reason = null;
            if ($binding && ! $this->truth->studentIdsMatch($profile['student_id'], $user->student_id)) {
                $reason = 'student_id_changed';
            } elseif (count($matches) !== 1 || $emailOwners->contains(fn ($id) => $id !== $user->id)) {
                $reason = 'cross_account_collision';
            } elseif (ExternalIdentity::where('connection_id', $connection->id)->where('user_id', $user->id)->where('identity_key', '!=', $key)->exists()) {
                $reason = 'binding_collision';
            }
            if ($reason) {
                $this->reviews->open($connection, $user, $claims['sub'], $profile, $reason, $binding !== null);
                $binding?->update(['enabled' => false]);

                return $blocked('sso_identity_review');
            }
            if (($claims['email_verified'] ?? null) !== true) {
                return $blocked('sso_not_eligible');
            }
            $expected = $binding?->accepted_claims ?? ['email' => strtolower($user->email), 'name' => $user->name];
            $different = $profile['email'] !== $expected['email']
                || $this->truth->normalizeComparable($profile['name']) !== $this->truth->normalizeComparable($expected['name']);
            if ($different) {
                $this->reviews->open($connection, $user, $claims['sub'], $profile, 'identity_data_changed', true);
            }
            $restricted = $this->reviews->restricted($user);
            if (! $restricted) {
                $binding ??= new ExternalIdentity(['connection_id' => $connection->id, 'user_id' => $user->id,
                    'identity_key' => $key, 'issuer' => $connection->issuer, 'subject' => $claims['sub'], 'accepted_claims' => $profile]);
                $binding->last_login_at = now();
                $binding->save();
            }

            return ['user' => $user, 'code' => $restricted ? 'sso_identity_review' : 'signed_in', 'under_verification' => $restricted];
        });
    }

    private function profile(OidcConnection $connection, array $claims): array
    {
        $id = $claims[$connection->student_id_claim] ?? null;
        $name = $claims['name'] ?? implode(' ', array_filter([$claims['given_name'] ?? null, $claims['family_name'] ?? null], 'is_string'));
        if (! is_string($id) || trim($id) === '' || strlen($id) > 100
            || ! is_string($claims['email'] ?? null) || strlen($claims['email']) > 255 || ! filter_var($claims['email'], FILTER_VALIDATE_EMAIL)
            || ! is_string($name) || trim($name) === '' || strlen($name) > 255) {
            throw new SsoException('sso_not_eligible');
        }

        return ['student_id' => trim($id), 'email' => strtolower(trim($claims['email'])), 'name' => trim($name)];
    }
}
