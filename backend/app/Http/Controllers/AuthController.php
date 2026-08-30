<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuthTokenService;
use App\Services\StudentOnboardingNavigator;
use App\Services\TotpService;
use App\Services\TwoFactorAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class AuthController extends Controller
{
    public function login(
        Request $request,
        StudentOnboardingNavigator $navigator,
        AuthTokenService $tokens,
        TwoFactorAuthService $twoFactor,
    ): JsonResponse {
        $bypassCaptcha = (bool) config('services.auth.dev_bypass_captcha', false);

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'captcha' => $bypassCaptcha ? ['nullable', 'string'] : ['required', 'string'],
        ]);

        if (! $this->captchaIsValid((string) ($credentials['captcha'] ?? ''))) {
            return response()->json([
                'message' => 'The security verification code is incorrect.',
                'errors' => ['captcha' => ['Failed to verify reCAPTCHA. Please try again.']],
            ], 422);
        }

        unset($credentials['captcha']);

        if (! Auth::attempt($credentials)) {
            return response()->json(['message' => 'The email or password is incorrect.'], 422);
        }

        $user = $request->user();

        // Pre-credential statuses cannot reach this point in practice (no usable
        // password exists), but fail closed and point the student at their link
        // rather than leaking that the account is mid-funnel.
        if (in_array($user->account_status, ['unverified', 'blocked', 'pending_kyc', 'pending_identity', 'pending_face_review', 'identity_verified', 'identity_rejected'], true)) {
            $statusMessages = [
                'unverified' => 'Your account has not been verified yet. Please check your email for the activation link.',
                'blocked' => 'Your account has been blocked. Please contact the administrator.',
                'identity_verified' => 'Finish setting your password using the link we emailed you.',
                'pending_face_review' => 'Your identity is under review. We will email you once a decision is made.',
            ];
            $message = $statusMessages[$user->account_status]
                ?? 'Finish activating your account using the link we emailed you.';

            Auth::logout();

            return response()->json(['message' => $message], 403);
        }

        Auth::logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        if ($twoFactor->enabled($user)) {
            return response()->json([
                'two_factor_required' => true,
                ...$twoFactor->createChallenge($user, $request),
            ]);
        }

        return $this->completeLogin($user, $request, $navigator, $tokens, 'password');
    }

    public function verifyTwoFactor(
        Request $request,
        StudentOnboardingNavigator $navigator,
        AuthTokenService $tokens,
        TwoFactorAuthService $twoFactor,
    ): JsonResponse {
        $validated = $request->validate([
            'challenge_id' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:32'],
        ]);

        try {
            $user = $twoFactor->verifyChallenge(
                (string) $validated['challenge_id'],
                (string) $validated['code'],
                $request,
            );
        } catch (UnauthorizedHttpException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        if (in_array($user->account_status, ['unverified', 'blocked'], true)) {
            return response()->json([
                'message' => $user->account_status === 'blocked'
                    ? 'Your account has been blocked. Please contact the administrator.'
                    : 'Your account has not been verified yet. Please check your email for the activation link.',
            ], 403);
        }

        return $this->completeLogin($user, $request, $navigator, $tokens, 'two_factor');
    }

    public function twoFactorStatus(Request $request): JsonResponse
    {
        return response()->json([
            'enabled' => $request->user()->two_factor_confirmed_at !== null,
            'confirmed_at' => $request->user()->two_factor_confirmed_at?->toIso8601String(),
            'recovery_codes_remaining' => count($request->user()->two_factor_recovery_codes ?? []),
        ]);
    }

    public function twoFactorSetup(Request $request, TotpService $totp): JsonResponse
    {
        $secret = $totp->generateSecret();
        $issuer = (string) config('app.name', 'UniFAST TES');

        return response()->json([
            'secret' => $secret,
            'otpauth_uri' => $totp->otpauthUri($secret, $request->user()->email, $issuer),
        ]);
    }

    public function twoFactorEnable(
        Request $request,
        TotpService $totp,
        TwoFactorAuthService $twoFactor,
    ): JsonResponse {
        $validated = $request->validate([
            'secret' => ['required', 'string', 'min:16', 'max:64'],
            'code' => ['required', 'string', 'max:32'],
        ]);

        if (! $totp->verify((string) $validated['secret'], (string) $validated['code'])) {
            return response()->json([
                'message' => 'The authenticator code is invalid.',
                'errors' => ['code' => ['Enter the current six-digit code from your authenticator app.']],
            ], 422);
        }

        $codes = $totp->recoveryCodes();
        $request->user()->forceFill([
            'two_factor_secret' => (string) $validated['secret'],
            'two_factor_recovery_codes' => $twoFactor->hashRecoveryCodes($codes),
            'two_factor_confirmed_at' => now(),
        ])->save();

        AuditLog::create([
            'actor' => $request->user()->name,
            'role' => ucfirst($request->user()->role),
            'action' => 'auth_2fa_enabled',
            'module' => 'Authentication',
            'target' => $request->user()->email,
            'context' => ['method' => 'totp'],
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'enabled' => true,
            'recovery_codes' => $codes,
        ]);
    }

    public function twoFactorDisable(Request $request, AuthTokenService $tokens): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check((string) $validated['password'], $request->user()->password)) {
            return response()->json([
                'message' => 'The password is incorrect.',
                'errors' => ['password' => ['Enter your current password.']],
            ], 422);
        }

        $request->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
        $tokens->revokeAll($request->user());

        AuditLog::create([
            'actor' => $request->user()->name,
            'role' => ucfirst($request->user()->role),
            'action' => 'auth_2fa_disabled',
            'module' => 'Authentication',
            'target' => $request->user()->email,
            'context' => ['revoked_sessions' => true],
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['enabled' => false, 'message' => 'Two-factor authentication disabled.']);
    }

    public function googleRedirect(Request $request): JsonResponse
    {
        if (! config('services.google.client_id') || ! config('services.google.client_secret')) {
            return response()->json(['message' => 'Google OAuth is not configured.'], 503);
        }

        $state = Str::random(40);
        $request->session()->put('google_oauth_state', $state);

        $url = 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => $this->googleRedirectUri(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ], '', '&', PHP_QUERY_RFC3986);

        return response()->json(['url' => $url]);
    }

    public function googleCallback(
        Request $request,
        StudentOnboardingNavigator $navigator,
        AuthTokenService $tokens,
        TwoFactorAuthService $twoFactor,
    ) {
        $state = (string) $request->query('state', '');
        if ($state === '' || ! hash_equals((string) $request->session()->pull('google_oauth_state', ''), $state)) {
            return redirect($this->frontendAuthUrl(['oauth_error' => 'state']));
        }

        $code = (string) $request->query('code', '');
        if ($code === '') {
            return redirect($this->frontendAuthUrl(['oauth_error' => 'denied']));
        }

        $token = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->googleRedirectUri(),
        ]);

        if (! $token->successful()) {
            return redirect($this->frontendAuthUrl(['oauth_error' => 'token']));
        }

        $profile = Http::withToken((string) $token->json('access_token'))
            ->acceptJson()
            ->get('https://openidconnect.googleapis.com/v1/userinfo');

        if (! $profile->successful() || ! $profile->json('email') || $profile->json('email_verified') !== true) {
            return redirect($this->frontendAuthUrl(['oauth_error' => 'email']));
        }

        $user = User::query()
            ->where('google_id', $profile->json('sub'))
            ->orWhere('email', $profile->json('email'))
            ->first();

        if (! $user) {
            return redirect($this->frontendAuthUrl(['oauth_error' => 'unknown']));
        }

        if (in_array($user->account_status, ['unverified', 'blocked'], true)) {
            return redirect($this->frontendAuthUrl(['oauth_error' => 'inactive']));
        }

        $user->forceFill([
            'google_id' => (string) $profile->json('sub'),
            'google_email_verified_at' => now(),
            'email_verified_at' => $user->email_verified_at ?: now(),
        ])->save();

        if ($twoFactor->enabled($user)) {
            $challenge = $twoFactor->createChallenge($user, $request);
            return redirect($this->frontendAuthUrl([
                'oauth_2fa' => $challenge['challenge_id'],
                'expires_at' => $challenge['expires_at'],
            ]));
        }

        $this->completeLogin($user, $request, $navigator, $tokens, 'google');

        return redirect($this->frontendAuthUrl(['signed_in' => 'google']));
    }

    public function refresh(
        Request $request,
        StudentOnboardingNavigator $navigator,
        AuthTokenService $tokens,
    ): JsonResponse {
        try {
            $user = $tokens->rotate($request);
        } catch (UnauthorizedHttpException) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return response()->json([
            'user' => $this->presentUser($user, $navigator),
        ]);
    }

    public function me(Request $request, StudentOnboardingNavigator $navigator): JsonResponse
    {
        return $request->user()
            ? response()->json(['user' => $this->presentUser($request->user(), $navigator)])
            : response()->json(['message' => 'Unauthenticated.'], 401);
    }

    public function logout(Request $request, AuthTokenService $tokens): JsonResponse
    {
        if ($request->user()) {
            AuditLog::create([
                'actor' => $request->user()->name,
                'role' => ucfirst($request->user()->role),
                'action' => 'auth_logout',
                'module' => 'Authentication',
                'target' => $request->user()->email,
                'context' => ['method' => 'cookie_access_refresh'],
                'ip_address' => $request->ip(),
            ]);
        }

        $tokens->revokeCurrent($request);

        return response()->json(['message' => 'Signed out.']);
    }

    private function presentUser(User $user, StudentOnboardingNavigator $navigator): array
    {
        $user->loadMissing(['kycProfile', 'grantee.identityProfile']);

        $payload = [
            ...$user->only('id', 'name', 'email', 'role', 'student_id', 'account_status'),
            'kyc_status' => $user->kycProfile?->status,
            'has_security_pin' => ! empty($user->security_pin),
        ];

        if ($user->role === 'student') {
            $next = $navigator->nextStep($user, $user->grantee);
            $payload['onboarding_next_step'] = $next;
            $payload['onboarding_path'] = $navigator->frontendPath($next);
        }

        return $payload;
    }

    private function completeLogin(
        User $user,
        Request $request,
        StudentOnboardingNavigator $navigator,
        AuthTokenService $tokens,
        string $method,
    ): JsonResponse {
        $tokens->issuePair($user, $request);

        AuditLog::create([
            'actor'      => $user->name,
            'role'       => ucfirst($user->role),
            'action'     => 'auth_login',
            'module'     => 'Authentication',
            'target'     => $user->email,
            'context'    => ['method' => $method],
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'user' => $this->presentUser($user, $navigator),
        ]);
    }

    private function googleRedirectUri(): string
    {
        $redirect = (string) config('services.google.redirect', '/api/auth/google/callback');

        return str_starts_with($redirect, 'http')
            ? $redirect
            : url($redirect);
    }

    private function frontendAuthUrl(array $query): string
    {
        $base = (string) config('services.auth.frontend_url');
        $path = '/login';
        $separator = '?';
        $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        return ($base !== '' ? $base : '').$path.($queryString !== '' ? $separator.$queryString : '');
    }

    private function captchaIsValid(string $input): bool
    {
        if ((bool) config('services.auth.dev_bypass_captcha', false)) {
            return true;
        }

        $secret = (string) config('services.recaptcha.secret');

        if ($secret === '') {
            // Failsafe in case the key isn't configured in production
            return false;
        }

        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => $secret,
            'response' => $input,
        ]);

        return $response->json('success') === true;
    }
}
