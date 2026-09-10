<?php

namespace App\Services\Sso;

use App\Models\ExternalIdentity;
use App\Models\OidcConnection;
use App\Models\RefreshToken;
use App\Models\SsoIdentityReview;
use App\Models\User;
use App\Services\AuthTokenService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SsoRolloutService
{
    public function save(array $data): OidcConnection
    {
        return DB::transaction(function () use ($data) {
            $connection = OidcConnection::query()->lockForUpdate()->first() ?? new OidcConnection(['singleton_key' => 'sis']);
            $changed = $connection->exists && collect(['issuer', 'client_id', 'student_id_claim', 'client_secret'])
                ->contains(fn ($key) => isset($data[$key]) && $data[$key] !== $connection->$key);
            if ($changed && ! ($data['confirm'] ?? false)) {
                throw ValidationException::withMessages(['confirm' => 'Confirm replacement. Existing bindings will be disabled and linked sessions revoked.']);
            }
            if ($changed) {
                $this->revokeLinked($connection);
                // A replacement begins a distinct trust relationship. Removing
                // bindings here deliberately requires a fresh, independently
                // verified student-ID/email match during the replacement pilot.
                ExternalIdentity::where('connection_id', $connection->id)->delete();
                $connection->revision++;
                $connection->pilot_successes = 0;
                $connection->pilot_failures = 0;
                DB::table('sso_pilot_users')->where('connection_id', $connection->id)->update(['successes' => 0, 'failures' => 0]);
            }
            unset($data['confirm']);
            $connection->fill($data);
            if (! $connection->exists || $changed) {
                $connection->fill(['state' => 'draft', 'validated_at' => null, 'jwks_refreshed_at' => null, 'error_code' => null]);
            }
            $connection->save();
            SsoAudit::record('configuration_saved', ['connection_id' => $connection->id, 'secret_replaced' => isset($data['client_secret'])]);

            return $connection;
        });
    }

    public function rollout(array $data): OidcConnection
    {
        return DB::transaction(function () use ($data) {
            $connection = OidcConnection::query()->lockForUpdate()->firstOrFail();
            $mode = $data['mode'];
            if ($mode !== 'disabled' && (! $connection->validated_at || $connection->error_code)) {
                throw ValidationException::withMessages(['mode' => 'Validate the connection before enabling login.']);
            }
            if ($mode === 'all_students' && (! ($data['confirm'] ?? false) || $connection->pilot_successes < 1 || SsoIdentityReview::where('status', 'pending')->exists())) {
                throw ValidationException::withMessages(['mode' => 'A successful pilot, no pending reviews, and explicit confirmation are required.']);
            }
            if ($mode === 'disabled') {
                if (! in_array($data['session_policy'] ?? '', ['keep', 'revoke'], true)) {
                    throw ValidationException::withMessages(['session_policy' => 'Choose whether existing sessions are kept or revoked.']);
                }
                if ($data['session_policy'] === 'revoke') {
                    $this->revokeLinked($connection);
                }
            }
            $connection->fill(['state' => $mode, 'revision' => $connection->revision + 1])->save();
            SsoAudit::record('rollout_changed', ['mode' => $mode, 'session_policy' => $data['session_policy'] ?? null]);

            return $connection;
        });
    }

    public function revokeLinked(OidcConnection $connection): void
    {
        // Include temporary under-verification sessions: they intentionally do
        // not have a durable binding yet, but are still SSO-issued and revocable.
        $ids = ExternalIdentity::where('connection_id', $connection->id)->pluck('user_id')
            ->merge(RefreshToken::where('sso_connection_id', $connection->id)->pluck('user_id'))
            ->unique();
        foreach (User::whereIn('id', $ids)->get() as $user) {
            app(AuthTokenService::class)->revokeAll($user);
        }
    }

    public static function studentAllowed(User $user): bool
    {
        return $user->role === 'student'
            && ! $user->roles()->where('name', '!=', 'student')->exists()
            && ! in_array($user->account_status, ['blocked', 'suspended', 'inactive', 'disabled'], true);
    }
}
