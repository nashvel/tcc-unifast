<?php

namespace App\Services;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class AuthTokenService
{
    /** Ability granted to pre-credential identity-funnel sessions. */
    public const ONBOARDING_ABILITY = 'onboarding:identity';

    public function accessCookieName(): string
    {
        return (string) config('services.auth.access_cookie', 'unifast_access');
    }

    public function refreshCookieName(): string
    {
        return (string) config('services.auth.refresh_cookie', 'unifast_refresh');
    }

    public function accessTtlMinutes(): int
    {
        return max(1, (int) config('services.auth.access_token_ttl_minutes', 20));
    }

    public function refreshTtlDays(): int
    {
        return max(1, (int) config('services.auth.refresh_token_ttl_days', 7));
    }

    public function onboardingSessionTtlMinutes(): int
    {
        return max(1, (int) config('services.auth.onboarding_session_ttl_minutes', 30));
    }

    /**
     * Abilities granted for a session scope. Full sessions keep ['*'], so every
     * existing ability check (including `ability:` middleware) passes unchanged.
     *
     * @return list<string>
     */
    public static function abilitiesFor(string $scope): array
    {
        return $scope === RefreshToken::SCOPE_ONBOARDING
            ? [self::ONBOARDING_ABILITY]
            : ['*'];
    }

    /**
     * Issue a short-lived Sanctum access token + rotating refresh token as HttpOnly cookies.
     */
    public function issuePair(
        User $user,
        Request $request,
        ?string $familyId = null,
        string $scope = RefreshToken::SCOPE_FULL,
    ): void {
        $accessMinutes = $this->accessTtlMinutes();
        $refreshDays = $this->refreshTtlDays();
        $familyId = $familyId ?? (string) Str::uuid();

        $access = $user->createToken('access', self::abilitiesFor($scope), now()->addMinutes($accessMinutes));
        $plainRefresh = Str::random(64);

        RefreshToken::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainRefresh),
            'family_id' => $familyId,
            'scope' => $scope,
            'expires_at' => now()->addDays($refreshDays),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 512, ''),
        ]);

        $this->queueAccessCookie($access->plainTextToken, $accessMinutes);
        $this->queueRefreshCookie($plainRefresh, $refreshDays * 24 * 60);
    }

    /**
     * Issue a pre-credential onboarding session for the identity funnel.
     *
     * Deliberately creates NO refresh token row: with nothing to rotate, an
     * onboarding session structurally cannot be escalated into a full one
     * (invariant I3). Resuming means re-opening the activation link.
     *
     * @return int the Sanctum PAT id, so callers can track/revoke the live session
     */
    public function issueOnboardingSession(User $user, Request $request): int
    {
        $minutes = $this->onboardingSessionTtlMinutes();

        $access = $user->createToken(
            'onboarding',
            self::abilitiesFor(RefreshToken::SCOPE_ONBOARDING),
            now()->addMinutes($minutes),
        );

        $this->queueAccessCookie($access->plainTextToken, $minutes);
        // No refresh cookie: clear any stale one so a prior full session cannot
        // be resurrected by rotation while the user is mid-funnel.
        Cookie::queue(Cookie::forget($this->refreshCookieName()));

        return (int) $access->accessToken->getKey();
    }

    /**
     * Promote a verified user from an onboarding session to a full session.
     * Called once, at credential creation.
     */
    public function upgradeToFullSession(User $user, Request $request): void
    {
        $this->revokeAll($user);
        $this->issuePair($user, $request, null, RefreshToken::SCOPE_FULL);
    }

    /**
     * Rotate refresh token; returns the authenticated user.
     *
     * @throws UnauthorizedHttpException
     */
    public function rotate(Request $request): User
    {
        $plain = (string) $request->cookie($this->refreshCookieName(), '');
        if ($plain === '') {
            $this->clearCookies();
            throw new UnauthorizedHttpException('Bearer', 'Unauthenticated.');
        }

        $hash = hash('sha256', $plain);
        $row = RefreshToken::query()->where('token_hash', $hash)->first();

        if (! $row) {
            $this->clearCookies();
            throw new UnauthorizedHttpException('Bearer', 'Unauthenticated.');
        }

        if ($row->revoked_at !== null) {
            // Reuse of a rotated refresh token → revoke the whole family.
            $this->revokeFamily((int) $row->user_id, (string) $row->family_id);
            $user = User::query()->find($row->user_id);
            $user?->tokens()->delete();
            $this->clearCookies();
            throw new UnauthorizedHttpException('Bearer', 'Unauthenticated.');
        }

        if ($row->expires_at->isPast()) {
            $row->forceFill(['revoked_at' => now()])->save();
            $this->clearCookies();
            throw new UnauthorizedHttpException('Bearer', 'Unauthenticated.');
        }

        /** @var User $user */
        $user = $row->user()->firstOrFail();

        $row->update(['revoked_at' => now()]);
        $user->tokens()->delete();

        // Carry the scope forward rather than re-deriving it: rotation must never
        // widen privileges (invariant I3).
        $this->issuePair($user, $request, (string) $row->family_id, (string) ($row->scope ?: RefreshToken::SCOPE_FULL));

        $replacement = RefreshToken::query()
            ->where('user_id', $user->id)
            ->where('family_id', $row->family_id)
            ->whereNull('revoked_at')
            ->latest('id')
            ->first();

        if ($replacement) {
            RefreshToken::query()->whereKey($row->id)->update([
                'replaced_by' => $replacement->id,
            ]);
        }

        return $user;
    }

    public function revokeCurrent(Request $request): void
    {
        // Drop every Sanctum PAT for this user on logout of this browser session.
        // Refresh-family revoke below is what stops silent re-issue on this device.
        if ($request->user()) {
            $request->user()->tokens()->delete();
        } else {
            $access = (string) ($request->bearerToken() ?: $request->cookie($this->accessCookieName(), ''));
            if ($access !== '') {
                PersonalAccessToken::findToken($access)?->delete();
            }
        }

        $plain = (string) $request->cookie($this->refreshCookieName(), '');
        if ($plain !== '') {
            $row = RefreshToken::query()
                ->where('token_hash', hash('sha256', $plain))
                ->first();
            if ($row) {
                $this->revokeFamily((int) $row->user_id, (string) $row->family_id);
            }
        }

        $this->clearCookies();
    }

    public function revokeAll(User $user): void
    {
        $user->tokens()->delete();
        RefreshToken::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
        $this->clearCookies();
    }

    public function revokeFamily(int $userId, string $familyId): void
    {
        RefreshToken::query()
            ->where('user_id', $userId)
            ->where('family_id', $familyId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function clearCookies(): void
    {
        Cookie::queue(Cookie::forget($this->accessCookieName()));
        Cookie::queue(Cookie::forget($this->refreshCookieName()));
    }

    private function queueAccessCookie(string $plainTextToken, int $minutes): void
    {
        Cookie::queue($this->makeCookie($this->accessCookieName(), $plainTextToken, $minutes));
    }

    private function queueRefreshCookie(string $plainRefresh, int $minutes): void
    {
        Cookie::queue($this->makeCookie($this->refreshCookieName(), $plainRefresh, $minutes));
    }

    private function makeCookie(string $name, string $value, int $minutes): \Symfony\Component\HttpFoundation\Cookie
    {
        $sameSite = strtolower((string) config('services.auth.cookie_same_site', 'lax'));
        if (! in_array($sameSite, ['lax', 'strict', 'none'], true)) {
            $sameSite = 'lax';
        }

        $secure = (bool) config('services.auth.cookie_secure');
        if ($sameSite === 'none') {
            $secure = true;
        }

        return cookie(
            name: $name,
            value: $value,
            minutes: $minutes,
            path: '/',
            domain: config('services.auth.cookie_domain'),
            secure: $secure,
            httpOnly: true,
            raw: false,
            sameSite: $sameSite,
        );
    }
}
