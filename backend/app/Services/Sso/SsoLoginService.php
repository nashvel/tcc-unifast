<?php

namespace App\Services\Sso;

use App\Models\ExternalIdentity;
use App\Models\OidcConnection;
use App\Models\User;
use App\Services\AuthTokenService;
use App\Services\TwoFactorAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SsoLoginService
{
    public function __construct(private OidcAuthorizationService $authorization, private OidcTokenValidator $validator,
        private SsoIdentityMatcher $matcher, private AuthTokenService $tokens, private TwoFactorAuthService $twoFactor,
        private SsoPilotService $pilots) {}

    public function callback(Request $request): string
    {
        $transaction = $this->authorization->consume($request);
        $connection = OidcConnection::findOrFail($transaction->connection_id);
        try {
            if ($request->has('error')) {
                throw new SsoException('sso_not_eligible');
            }
            $code = $request->query('code');
            if (! is_string($code) || $code === '' || strlen($code) > 4096
                || ($request->has('iss') && $request->query('iss') !== $connection->issuer)) {
                throw new SsoException('sso_invalid_state');
            }
            $this->checkConnection($connection, $transaction->revision, $transaction->pilot_user_id);
            $claims = $this->validator->exchange($connection, $code, $transaction->context);
            $result = DB::transaction(function () use ($connection, $transaction, $claims, $request) {
                $connection = OidcConnection::whereKey($connection->id)->lockForUpdate()->firstOrFail();
                $this->checkConnection($connection, $transaction->revision, $transaction->pilot_user_id);
                $result = $this->matcher->match($connection, $claims, $transaction->pilot_user_id);
                if (! $result['user']) {
                    return $result['code'];
                }
                $user = $result['user'];
                $this->tokens->clearCookies();
                $request->session()->regenerate();
                if ($this->twoFactor->enabled($user)) {
                    $challenge = $this->twoFactor->createChallenge($user, $request);
                    $request->session()->put('sso_two_factor', ['challenge_id' => $challenge['challenge_id'],
                        'connection_id' => $connection->id, 'revision' => $connection->revision, 'pilot_user_id' => $transaction->pilot_user_id,
                        'remember_me' => $transaction->context['remember_me']]);

                    return 'two_factor';
                }
                $this->complete($connection, $user, $request, $transaction->context['remember_me'], $transaction->pilot_user_id, $result['under_verification']);

                return $result['under_verification'] ? 'under_verification' : 'signed_in';
            });
            if (! in_array($result, ['two_factor', 'signed_in', 'under_verification'], true)) {
                throw new SsoException($result);
            }

            return $result;
        } catch (SsoException $error) {
            $this->pilots->outcome($connection, $transaction->pilot_user_id, false);
            throw $error;
        }
    }

    public function verifyTwoFactor(Request $request, string $code): User
    {
        $context = $request->session()->get('sso_two_factor');
        if (! is_array($context)) {
            throw new SsoException('sso_not_eligible');
        }

        return DB::transaction(function () use ($request, $code, $context) {
            $connection = OidcConnection::whereKey($context['connection_id'])->lockForUpdate()->firstOrFail();
            $this->checkConnection($connection, $context['revision'], $context['pilot_user_id']);
            $user = $this->twoFactor->verifyChallenge($context['challenge_id'], $code, $request);
            $request->session()->forget('sso_two_factor');
            if (! SsoRolloutService::studentAllowed($user)) {
                throw new SsoException('sso_account_blocked');
            }
            $restricted = app(SsoIdentityReviewService::class)->restricted($user);
            if (! $restricted && ! ExternalIdentity::where('connection_id', $connection->id)->where('user_id', $user->id)->where('enabled', true)->exists()) {
                throw new SsoException('sso_account_blocked');
            }
            $this->complete($connection, $user, $request, $context['remember_me'], $context['pilot_user_id'], $restricted);

            return $user;
        });
    }

    private function complete(OidcConnection $connection, User $user, Request $request, bool $remember, ?int $pilotUser, bool $restricted): void
    {
        $this->tokens->issuePair($user, $request, rememberMe: $remember, ssoConnectionId: $connection->id);
        if (! $restricted) {
            $this->pilots->outcome($connection, $pilotUser, true);
        }
        SsoAudit::record('login_completed', ['connection_id' => $connection->id, 'user_id' => $user->id]);
    }

    private function checkConnection(OidcConnection $connection, int $revision, ?int $pilotUser): void
    {
        if (! $connection->available() || $connection->revision !== $revision
            || ($pilotUser === null && $connection->state !== 'all_students')
            || ($pilotUser !== null && ! DB::table('sso_pilot_users')->where('connection_id', $connection->id)->where('user_id', $pilotUser)->exists())) {
            throw new SsoException;
        }
    }
}
