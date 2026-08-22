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
        $assignedRoles = $user->roles;
        $allowed = $assignedRoles->isNotEmpty()
            ? $assignedRoles->contains(fn ($role) => in_array($role->name, $roles, true))
            : in_array($user->role, $roles, true);

        abort_unless($allowed, 403);

        return $next($request);
    }
}
