<?php

namespace App\Http\Controllers;

use App\Models\ActivationToken;
use App\Models\AuditLog;
use App\Services\AuthTokenService;
use App\Services\StudentOnboardingNavigator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Terminal step of identity-first onboarding: the student sets their password
 * AFTER identity has been proven (liveness auto-pass or staff approval).
 *
 * This is the only place a student-chosen password is ever written, which is what
 * makes the biometric check the gate on account ownership rather than on document
 * upload (invariant I1).
 */
class OnboardingCredentialController extends Controller
{
    public function store(
        Request $request,
        StudentOnboardingNavigator $navigator,
        AuthTokenService $tokens,
    ): JsonResponse {
        $user = $request->user();

        if ($user->account_status === 'pending_face_review') {
            throw ValidationException::withMessages([
                'account_status' => 'Your face match is under staff review. You will receive an email once a decision is made.',
            ]);
        }

        if ($user->account_status !== 'identity_verified') {
            throw ValidationException::withMessages([
                'account_status' => 'Complete identity verification before setting your password.',
            ]);
        }

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'account_status' => 'active',
            'activated_at' => now(),
            'email_verified_at' => now(),
        ])->save();

        // Spend the activation token now — not at link click — and release the
        // onboarding session reference.
        ActivationToken::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->update(['used_at' => now(), 'onboarding_session_id' => null]);

        // Revokes the onboarding-scoped token and issues full access + refresh cookies.
        $tokens->upgradeToFullSession($user->fresh(), $request);

        $user = $user->fresh();
        $next = $navigator->nextStep($user);

        AuditLog::create([
            'actor' => $user->name,
            'role' => 'Student',
            'action' => 'onboarding_credentials_created',
            'module' => 'Identity Onboarding',
            'target' => "User #{$user->id}",
            'context' => [
                'account_status' => $user->account_status,
                'credential_created_after_identity_verification' => true,
            ],
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'user' => [
                ...$user->only('id', 'name', 'email', 'role', 'student_id', 'account_status'),
                'kyc_status' => $user->kycProfile?->status,
                'has_security_pin' => ! empty($user->security_pin),
                'onboarding_next_step' => $next,
                'onboarding_path' => $navigator->frontendPath($next),
            ],
            'message' => 'Password set. Your account is now active.',
        ]);
    }
}
