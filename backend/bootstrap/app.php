<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['web', \App\Http\Middleware\AuthenticateFromAccessCookie::class, 'auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Railway/Vercel terminate TLS at their edge and forward over HTTP.
        // Without this, generated URLs (activation emails, signed routes) use http://.
        $middleware->trustProxies(at: '*');
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->alias(['role' => \App\Http\Middleware\RequireRole::class]);
        $middleware->web(append: [\App\Http\Middleware\SetLocaleFromUrl::class]);
        // Decrypt cookies + promote access cookie → Bearer for Sanctum on all API routes.
        // Access/refresh auth cookies stay unencrypted so Sanctum can use the raw PAT;
        // XSS protection is HttpOnly + Secure + SameSite (not Laravel cookie encryption).
        $middleware->encryptCookies(except: [
            env('AUTH_ACCESS_COOKIE', 'unifast_access'),
            env('AUTH_REFRESH_COOKIE', 'unifast_refresh'),
        ]);
        $middleware->api(prepend: [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \App\Http\Middleware\AuthenticateFromAccessCookie::class,
        ]);
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
