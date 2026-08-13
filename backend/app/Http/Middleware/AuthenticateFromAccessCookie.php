<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Promote the HttpOnly access cookie into an Authorization Bearer header
 * so existing auth:sanctum routes keep working without localStorage tokens.
 */
class AuthenticateFromAccessCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->bearerToken()) {
            $cookieName = (string) config('services.auth.access_cookie', 'unifast_access');
            $access = (string) $request->cookie($cookieName, '');
            if ($access !== '') {
                $request->headers->set('Authorization', 'Bearer '.$access);
            }
        }

        return $next($request);
    }
}
