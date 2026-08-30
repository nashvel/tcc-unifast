<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reject pre-credential onboarding sessions on everything outside the identity funnel.
 *
 * Onboarding sessions are always Sanctum personal access tokens carrying only the
 * `onboarding:identity` ability (see AuthTokenService::issueOnboardingSession), so
 * inspecting the access token is sufficient to catch every one of them.
 *
 * Three cases:
 *   - PAT with ['*']        → full session, allowed (invariant I6)
 *   - PAT with onboarding   → rejected (invariant I2)
 *   - no token / transient  → session-guard auth, which cannot be scope-limited;
 *                             allowed, and `auth:sanctum` has already run
 *
 * The last case matters because Sanctum's stateful/session path yields a
 * TransientToken (whose `can()` is always true) or no token at all. Rejecting it
 * would break session-authenticated requests without adding protection: a
 * scope-limited session always has a real PAT.
 *
 * See docs/identity-first-activation-implementation-plan.md (invariant I2).
 */
class EnsureFullSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        // Only a real PAT can be scope-limited; anything else is not an
        // onboarding session.
        if ($token && ! $user->tokenCan('*')) {
            abort(403, 'Complete identity verification and set your password before using this feature.');
        }

        return $next($request);
    }
}
