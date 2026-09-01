<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Single source of truth for grantee activation URLs.
 *
 * Previously duplicated across four controllers, each reading env() directly.
 * env() returns null once `artisan config:cache` has run, which silently
 * degraded every activation link to the localhost fallback in production.
 */
class ActivationLink
{
    public static function base(): string
    {
        $cached = Cache::get('runtime_activation_frontend_url');
        if (! empty($cached)) {
            return rtrim((string) $cached, '/');
        }

        $base = (string) (
            config('app.activation_frontend_url')
            ?: config('app.frontend_url')
            ?: 'http://localhost:5173'
        );

        // FRONTEND_URL may hold a comma-separated CORS list (see config/cors.php);
        // activation links need exactly one origin, so take the first entry.
        $first = trim(explode(',', $base)[0]);

        return rtrim($first !== '' ? $first : 'http://localhost:5173', '/');
    }

    public static function for(string $plainToken, string $lang = 'en'): string
    {
        return self::base().'/activate/'.$plainToken.'?lang='.$lang;
    }
}
