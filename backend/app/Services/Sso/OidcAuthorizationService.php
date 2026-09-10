<?php

namespace App\Services\Sso;

use App\Models\OidcConnection;
use App\Models\SsoLoginTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OidcAuthorizationService
{
    public function __construct(private OidcDiscoveryService $discovery) {}

    public function start(OidcConnection $connection, Request $request, ?string $pilotToken): string
    {
        try {
            $metadata = $this->discovery->metadata($connection);
            $this->discovery->keys($connection);
        } catch (\Throwable) {
            $this->discovery->unhealthy($connection);
            throw new SsoException;
        }

        return DB::transaction(function () use ($connection, $request, $pilotToken, $metadata) {
            $connection = OidcConnection::whereKey($connection->id)->lockForUpdate()->firstOrFail();
            if (! $connection->available() || ($pilotToken === null && $connection->state !== 'all_students')) {
                throw new SsoException;
            }
            $pilotUser = null;
            if ($pilotToken !== null) {
                $invite = DB::table('sso_pilot_invitations')->where('token_hash', hash('sha256', $pilotToken))->lockForUpdate()->first();
                if (! $invite || $invite->consumed_at || $invite->expires_at <= now()->toDateTimeString()
                    || $invite->revision !== $connection->revision || $invite->connection_id !== $connection->id
                    || ! DB::table('sso_pilot_users')->where('connection_id', $connection->id)->where('user_id', $invite->user_id)->exists()) {
                    throw new SsoException('sso_not_eligible');
                }
                $pilotUser = $invite->user_id;
                DB::table('sso_pilot_invitations')->where('id', $invite->id)->update(['consumed_at' => now()]);
            }
            $state = Str::random(64);
            $verifier = Str::random(64);
            $browser = $request->session()->get('sso_browser');
            if (! is_string($browser)) {
                $browser = Str::random(64);
                $request->session()->put('sso_browser', $browser);
            }
            $context = ['nonce' => Str::random(64), 'verifier' => $verifier, 'remember_me' => $request->boolean('remember_me'), 'redirect_uri' => url('/api/auth/sis/callback')];
            SsoLoginTransaction::create(['connection_id' => $connection->id, 'pilot_user_id' => $pilotUser,
                'state_hash' => hash('sha256', $state), 'browser_hash' => hash('sha256', $browser), 'revision' => $connection->revision,
                'context' => $context, 'expires_at' => now()->addMinutes(10)]);
            SsoAudit::record('login_started', ['connection_id' => $connection->id]);

            return $metadata['authorization_endpoint'].(str_contains($metadata['authorization_endpoint'], '?') ? '&' : '?').http_build_query([
                'client_id' => $connection->client_id, 'redirect_uri' => $context['redirect_uri'], 'response_type' => 'code',
                'scope' => 'openid profile email', 'state' => $state, 'nonce' => $context['nonce'],
                'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='), 'code_challenge_method' => 'S256',
            ], '', '&', PHP_QUERY_RFC3986);
        });
    }

    /** Consume and erase secrets even when a callback later fails validation. */
    public function consume(Request $request): SsoLoginTransaction
    {
        $state = $request->query('state');
        if (! is_string($state) || strlen($state) !== 64) {
            throw new SsoException('sso_invalid_state');
        }
        $transaction = DB::transaction(function () use ($state) {
            $row = SsoLoginTransaction::where('state_hash', hash('sha256', $state))->lockForUpdate()->first();
            if (! $row || $row->consumed_at !== null) {
                return null;
            }
            $context = $row->context;
            $row->forceFill(['consumed_at' => now(), 'context' => null])->save();
            $row->context = $context;

            return $row;
        });
        $browser = $request->session()->get('sso_browser', '');
        if (! $transaction || $transaction->expires_at->isPast() || ! is_string($browser) || $browser === ''
            || ! hash_equals($transaction->browser_hash, hash('sha256', $browser))) {
            throw new SsoException('sso_invalid_state');
        }

        return $transaction;
    }
}
