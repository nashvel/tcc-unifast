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

        // The developer role is a superuser — grant all permissions unconditionally.
        // This mirrors DatabaseViewerPolicy::currentUserCanViewDatabase() and keeps an
        // account that predates RBAC seeding from being locked out.
        if ($user->role === 'developer') {
            return $next($request);
        }

        // Also skip the check if the user holds a RBAC role whose permissions include
        // the wildcard '*' (i.e. another superuser-class role).
        $user->loadMissing('roles.permissions');
        $hasSuperRole = $user->roles->contains(
            fn ($role) => in_array('*', (array) ($role->permissions->pluck('name')->all()), true)
        );
        if ($hasSuperRole) {
            return $next($request);
        }

        // Fallback for accounts or tests with no RBAC pivot rows yet:
        if ($user->roles->isEmpty()) {
            if (in_array($user->role, ['admin', 'head', 'developer'], true)) {
                return $next($request);
            }
            if ($user->role === 'staff') {
                $staffPerms = [
                    'view_masterlist', 'manage_batches', 'manage_grantees',
                    'validate_documents', 'review_academics', 'run_eligibility',
                    'generate_reports', 'batches.read', 'documents.read', 'documents.write',
                    'grantees.read', 'academic.read',
                ];
                if (count(array_intersect($permissions, $staffPerms)) > 0) {
                    return $next($request);
                }
            }
        }

        abort_unless($user->hasAnyPermission($permissions), 403);

        return $next($request);
    }
}
