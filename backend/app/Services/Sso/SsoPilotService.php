<?php

namespace App\Services\Sso;

use App\Models\OidcConnection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SsoPilotService
{
    public function select(User $user, User $admin): void
    {
        if (! SsoRolloutService::studentAllowed($user)) {
            throw ValidationException::withMessages(['user_id' => 'Select an existing eligible student.']);
        }
        $connection = OidcConnection::firstOrFail();
        DB::table('sso_pilot_users')->updateOrInsert(['connection_id' => $connection->id, 'user_id' => $user->id],
            ['created_by' => $admin->id, 'created_at' => now(), 'updated_at' => now()]);
        SsoAudit::record('pilot_selected', ['user_id' => $user->id]);
    }

    public function remove(User $user): void
    {
        DB::transaction(function () use ($user) {
            $connection = OidcConnection::query()->lockForUpdate()->firstOrFail();
            DB::table('sso_pilot_users')->where('connection_id', $connection->id)->where('user_id', $user->id)->delete();
            DB::table('sso_pilot_invitations')->where('connection_id', $connection->id)->where('user_id', $user->id)->whereNull('consumed_at')->update(['consumed_at' => now()]);
            SsoAudit::record('pilot_removed', ['user_id' => $user->id]);
        });
    }

    public function invite(User $user, User $admin): array
    {
        return DB::transaction(function () use ($user, $admin) {
            $connection = OidcConnection::query()->lockForUpdate()->firstOrFail();
            if ($connection->state !== 'pilot' || ! $connection->available() || ! SsoRolloutService::studentAllowed($user)
                || ! DB::table('sso_pilot_users')->where('connection_id', $connection->id)->where('user_id', $user->id)->exists()) {
                throw ValidationException::withMessages(['user_id' => 'Enable pilot mode and select this student first.']);
            }
            $token = Str::random(64);
            $expires = now()->addDay();
            DB::table('sso_pilot_invitations')->insert(['connection_id' => $connection->id, 'user_id' => $user->id, 'created_by' => $admin->id,
                'token_hash' => hash('sha256', $token), 'revision' => $connection->revision, 'expires_at' => $expires, 'created_at' => now(), 'updated_at' => now()]);
            SsoAudit::record('pilot_invited', ['user_id' => $user->id]);

            // Fragment is removed by the pilot page before it performs any request.
            return ['url' => rtrim((string) config('services.auth.frontend_url'), '/').'/sis-pilot#'.$token, 'expires_at' => $expires->toIso8601String()];
        });
    }

    public function outcome(OidcConnection $connection, ?int $userId, bool $success): void
    {
        if ($userId === null) {
            return;
        }
        $connection->increment($success ? 'pilot_successes' : 'pilot_failures');
        DB::table('sso_pilot_users')->where('connection_id', $connection->id)->where('user_id', $userId)->increment($success ? 'successes' : 'failures');
    }
}
