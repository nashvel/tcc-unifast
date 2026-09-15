<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Sso\SsoIdentityReviewService;
use Closure;
use Illuminate\Http\Request;

class EnsureSsoIdentityAccess
{
    public function handle(Request $request, Closure $next)
    {
        // Resolve Sanctum explicitly: this API middleware runs before route auth.
        $user = auth('sanctum')->user();
        if ($user instanceof User && app(SsoIdentityReviewService::class)->restricted($user)
            && ! in_array($request->path(), ['api/auth/me', 'api/auth/logout', 'api/auth/refresh', 'api/auth/sis/status'], true)) {
            return response()->json(['code' => 'sso_identity_review', 'message' => 'Your SIS identity is under verification.'], 403);
        }

        return $next($request);
    }
}
