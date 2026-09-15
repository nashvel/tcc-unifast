<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureContinuityAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->account_status === 'active', 403, 'An active account is required.');

        return $next($request);
    }
}
