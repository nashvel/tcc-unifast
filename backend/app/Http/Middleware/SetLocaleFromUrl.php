<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleFromUrl
{
    public const SUPPORTED = ['en', 'tl', 'ceb'];

    public const FALLBACK = 'en';

    public function handle(Request $request, Closure $next): Response
    {
        $lang = $request->query('lang');
        $locale = is_string($lang) && in_array($lang, self::SUPPORTED, true)
            ? $lang
            : self::FALLBACK;

        app()->setLocale($locale);
        $request->attributes->set('locale', $locale);
        $request->attributes->set('available_locales', self::SUPPORTED);
        $request->attributes->set('fallback_locale', self::FALLBACK);

        View::share('locale', $locale);
        View::share('availableLocales', self::SUPPORTED);
        View::share('fallbackLocale', self::FALLBACK);

        return $next($request);
    }
}
