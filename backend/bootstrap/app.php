<?php

use App\Http\Middleware\AllowOnboardingAbility;
use App\Http\Middleware\AuthenticateFromAccessCookie;
use App\Http\Middleware\EnsureFullSession;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\RequireRole;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocaleFromUrl;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['web', AuthenticateFromAccessCookie::class, 'auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Railway/Vercel terminate TLS at their edge and forward over HTTP.
        // Without this, generated URLs (activation emails, signed routes) use http://.
        $middleware->trustProxies(at: '*');
        $middleware->append(SecurityHeaders::class);
        $middleware->alias([
            'role' => RequireRole::class,
            'permission' => RequirePermission::class,
            // Pre-credential onboarding sessions hold only 'onboarding:identity'.
            // 'full-session' keeps them out of everything else; 'ability' gates the
            // identity funnel itself. Sanctum does not register 'ability' by default.
            'full-session' => EnsureFullSession::class,
            'onboarding-session' => AllowOnboardingAbility::class,
            'ability' => CheckForAnyAbility::class,
        ]);
        $middleware->web(append: [SetLocaleFromUrl::class]);
        // Decrypt cookies + promote access cookie → Bearer for Sanctum on all API routes.
        // Access/refresh auth cookies stay unencrypted so Sanctum can use the raw PAT;
        // XSS protection is HttpOnly + Secure + SameSite (not Laravel cookie encryption).
        $middleware->encryptCookies(except: [
            env('AUTH_ACCESS_COOKIE', 'unifast_access'),
            env('AUTH_REFRESH_COOKIE', 'unifast_refresh'),
        ]);
        $middleware->api(prepend: [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            AuthenticateFromAccessCookie::class,
        ]);
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
