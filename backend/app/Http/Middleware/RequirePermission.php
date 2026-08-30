<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        abort_unless($user, 403);

        // Permissions come from pivot roles. The legacy `users.role` column is
        // honoured for developers so an account that predates RBAC seeding is not
        // locked out — this mirrors DatabaseViewerPolicy::currentUserCanViewDatabase().
        $user->loadMissing('roles.permissions');

        abort_unless(
            $user->role === 'developer' || $user->hasAnyPermission($permissions),
            403,
        );

        return $next($request);
    }
}
