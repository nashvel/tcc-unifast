<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_unless($user, 403);

        $user->loadMissing('roles');

        // Authorization uses the RBAC pivot table exclusively when any roles are assigned.
        // The legacy flat `user->role` string column is the fallback ONLY for accounts that
        // pre-date the RBAC migration and have no pivot rows yet.
        //
        // WARNING: these two systems can drift. If a user's RBAC roles are revoked via
        // DELETE /rbac/users/{user}/roles but `users.role` is not cleared, the fallback
        // will still grant access. Always update BOTH when changing a user's role.
        // TODO: once all accounts are migrated, remove the fallback and rely on RBAC only.
        $assignedRoles = $user->roles;
        $allowed = $assignedRoles->isNotEmpty()
            ? $assignedRoles->contains(fn ($role) => in_array($role->name, $roles, true))
            : in_array($user->role, $roles, true);

        abort_unless($allowed, 403);

        return $next($request);
    }
}
