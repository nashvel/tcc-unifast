<?php

namespace App\Http\Middleware;

use App\Services\AuthTokenService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate the identity funnel without rejecting session-authenticated requests.
 *
 * Sanctum's CheckForAnyAbility throws AuthenticationException whenever there is no
 * personal access token, which breaks the session/stateful guard: those requests
 * legitimately have no PAT (or a TransientToken). That turned ownership checks
 * inside the controllers into 401s before they could run.
 *
 * Only a real PAT can be scope-limited, so:
 *   - PAT with ['*']                    → full session, allowed
 *   - PAT with onboarding:identity      → allowed (this is the funnel)
 *   - PAT with anything else            → 403
 *   - no PAT (session guard)            → allowed; auth:sanctum already ran
 *
 * Mirrors EnsureFullSession, which solves the same problem from the other side.
 */
class AllowOnboardingAbility
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user, 403);

        $token = $user->currentAccessToken();

        if ($token && ! $user->tokenCan('*') && ! $user->tokenCan(AuthTokenService::ONBOARDING_ABILITY)) {
            abort(403, 'This session cannot access identity verification.');
        }

        return $next($request);
    }
}
