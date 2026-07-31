<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\StudentOnboardingNavigator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    private const CAPTCHA_SESSION_KEY = 'auth_captcha';

    public function login(Request $request, StudentOnboardingNavigator $navigator): JsonResponse
    {
        $bypassCaptcha = (bool) config('services.auth.dev_bypass_captcha', false);

        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
            'captcha'  => $bypassCaptcha ? ['nullable', 'string'] : ['required', 'string'],
        ]);

        if (! $this->captchaIsValid($request, (string) ($credentials['captcha'] ?? ''))) {
            $request->session()->forget(self::CAPTCHA_SESSION_KEY);

            return response()->json([
                'message' => 'The security verification code is incorrect or has expired.',
                'errors'  => ['captcha' => ['The security verification code is incorrect or has expired.']],
            ], 422);
        }

        $request->session()->forget(self::CAPTCHA_SESSION_KEY);
        unset($credentials['captcha']);

        if (! Auth::attempt($credentials)) {
            return response()->json(['message' => 'The email or password is incorrect.'], 422);
        }

        $user = $request->user();

        // Allow the post-activation funnel (KYC → identity onboarding) to sign in.
        // Only block unverified / blocked accounts from the normal login form.
        if (in_array($user->account_status, ['unverified', 'blocked'], true)) {
            $statusMessages = [
                'unverified' => 'Your account has not been verified yet. Please check your email for the activation link.',
                'blocked' => 'Your account has been blocked. Please contact the administrator.',
            ];
            $message = $statusMessages[$user->account_status]
                ?? 'Your account is not active. Please contact the administrator.';

            Auth::logout();

            return response()->json(['message' => $message], 403);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        AuditLog::create([
            'actor'      => $user->name,
            'role'       => ucfirst($user->role),
            'action'     => 'auth_login',
            'module'     => 'Authentication',
            'target'     => $user->email,
            'context'    => ['method' => 'sanctum_token'],
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'user'  => $this->presentUser($user, $navigator),
            'token' => $token,
        ]);
    }

    public function me(Request $request, StudentOnboardingNavigator $navigator): JsonResponse
    {
        return $request->user()
            ? response()->json(['user' => $this->presentUser($request->user(), $navigator)])
            : response()->json(['message' => 'Unauthenticated.'], 401);
    }

    public function logout(Request $request): JsonResponse
    {
        if ($request->user()) {
            AuditLog::create([
                'actor'      => $request->user()->name,
                'role'       => ucfirst($request->user()->role),
                'action'     => 'auth_logout',
                'module'     => 'Authentication',
                'target'     => $request->user()->email,
                'context'    => ['method' => 'sanctum_token'],
                'ip_address' => $request->ip(),
            ]);

            $request->user()->currentAccessToken()->delete();
        }

        return response()->json(['message' => 'Signed out.']);
    }

    private function presentUser(\App\Models\User $user, StudentOnboardingNavigator $navigator): array
    {
        $user->loadMissing(['kycProfile', 'grantee.identityProfile']);

        $payload = [
            ...$user->only('id', 'name', 'email', 'role', 'student_id', 'account_status'),
            'kyc_status' => $user->kycProfile?->status,
        ];

        if ($user->role === 'student') {
            $next = $navigator->nextStep($user, $user->grantee);
            $payload['onboarding_next_step'] = $next;
            $payload['onboarding_path'] = $navigator->frontendPath($next);
        }

        return $payload;
    }

    private function captchaIsValid(Request $request, string $input): bool
    {
        if ((bool) config('services.auth.dev_bypass_captcha', false)) {
            return true;
        }

        $captcha = $request->session()->get(self::CAPTCHA_SESSION_KEY);

        if (! is_array($captcha) || empty($captcha['code']) || empty($captcha['expires_at'])) {
            return false;
        }

        if ((int) $captcha['expires_at'] < now()->timestamp) {
            return false;
        }

        return hash_equals($captcha['code'], strtoupper(trim($input)));
    }
}
