<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\StudentOnboardingNavigator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
    public function login(Request $request, StudentOnboardingNavigator $navigator): JsonResponse
    {
        $bypassCaptcha = (bool) config('services.auth.dev_bypass_captcha', false);

        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
            'captcha'  => $bypassCaptcha ? ['nullable', 'string'] : ['required', 'string'],
        ]);

        if (! $this->captchaIsValid((string) ($credentials['captcha'] ?? ''))) {
            return response()->json([
                'message' => 'The security verification code is incorrect.',
                'errors'  => ['captcha' => ['Failed to verify reCAPTCHA. Please try again.']],
            ], 422);
        }

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

    private function captchaIsValid(string $input): bool
    {
        if ((bool) config('services.auth.dev_bypass_captcha', false)) {
            return true;
        }

        $secret = env('RECAPTCHA_SECRET_KEY');

        if (empty($secret)) {
            // Failsafe in case the key isn't configured in production
            return false;
        }

        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret'   => $secret,
            'response' => $input,
        ]);

        return $response->json('success') === true;
    }
}
