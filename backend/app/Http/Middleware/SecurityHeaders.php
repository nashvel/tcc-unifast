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
        $response = $next($request);

        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy());
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        // Allow same-origin iframe/embed for authenticated PDF previews; deny cross-origin framing.
        $frameOption = ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse)
            ? 'SAMEORIGIN'
            : 'DENY';
        $response->headers->set('X-Frame-Options', $frameOption);
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        return $response;
    }

    /**
     * Environment-aware CSP.
     *
     * Vite's dev server needs 'unsafe-eval' and localhost websocket origins for HMR;
     * shipping those to production widened the policy for no benefit. The built SPA
     * needs neither, so outside local both are dropped and connect-src is narrowed
     * to the configured frontend origins.
     *
     * 'unsafe-inline' on script-src is retained for now because the app still relies
     * on inline handlers; removing it needs a nonce/hash pass and is tracked
     * separately.
     */
    private function contentSecurityPolicy(): string
    {
        $isLocal = app()->environment(['local', 'testing']);

        $scriptSrc = $isLocal
            ? "script-src 'self' 'unsafe-inline' 'unsafe-eval'"
            : "script-src 'self' 'unsafe-inline'";

        $connectSrc = $isLocal
            ? "connect-src 'self' http://localhost:* ws://localhost:* http://127.0.0.1:* ws://127.0.0.1:*"
            : 'connect-src '.$this->productionConnectSources();

        return implode('; ', [
            "default-src 'self'",
            $scriptSrc,
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob: https:",
            "font-src 'self' data:",
            $connectSrc,
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);
    }

    /**
     * Self plus the configured SPA origins (FRONTEND_URL may hold a comma list),
     * with their websocket equivalents for Echo.
     */
    private function productionConnectSources(): string
    {
        $sources = ["'self'"];

        foreach (explode(',', (string) config('app.frontend_url')) as $origin) {
            $origin = rtrim(trim($origin), '/');
            if ($origin === '') {
                continue;
            }

            $sources[] = $origin;
            $sources[] = str_starts_with($origin, 'https://')
                ? 'wss://'.substr($origin, 8)
                : 'ws://'.preg_replace('#^https?://#', '', $origin);
        }

        return implode(' ', array_unique($sources));
    }
}
