<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Generate a per-request nonce for CSP. Stored on the request so Blade/views can emit it.
        $nonce = base64_encode(random_bytes(16));
        $request->attributes->set('csp_nonce', $nonce);

        $response = $next($request);

        // Nonce-based CSP: eliminates unsafe-inline and unsafe-eval.
        // 'unsafe-inline' is retained for style-src only because Vue SFC scoped styles
        // are injected at runtime; migrate to a build-time stylesheet to remove it.
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob: https:",
            "font-src 'self' data:",
            "connect-src 'self' http://localhost:* ws://localhost:* wss://localhost:*",
            "frame-ancestors 'none'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);
        $response->headers->set('Content-Security-Policy', $csp);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // Allow same-origin iframe/embed for authenticated PDF previews; deny cross-origin framing.
        $frameOption = ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse)
            ? 'SAMEORIGIN'
            : 'DENY';
        $response->headers->set('X-Frame-Options', $frameOption);

        // X-XSS-Protection is deprecated and removed — the CSP above replaces it.

        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        return $response;
    }
}
