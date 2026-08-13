<?php

namespace App\Http\Controllers;

use App\Models\ActivationToken;
use App\Services\AuthTokenService;
use App\Services\StudentOnboardingNavigator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ActivationController extends Controller
{
    public function show(string $token): JsonResponse
    {
        $activation = $this->findValidToken($token);
        $user = $activation->user()->with('grantee')->firstOrFail();

        return response()->json([
            'data' => [
                'email' => $user->email,
                'name' => $user->name,
                'student_id' => $user->student_id,
                'program' => $user->grantee?->program,
                'expires_at' => $activation->expires_at,
            ],
        ]);
    }

    public function activate(
        Request $request,
        string $token,
        StudentOnboardingNavigator $navigator,
        AuthTokenService $tokens,
    ): JsonResponse {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Token is the sole proof of invitation (unused + unexpired). No temporary password.
        $activation = $this->findValidToken($token);
        $user = $activation->user()->firstOrFail();

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'account_status' => 'pending_kyc',
            'activated_at' => now(),
            'email_verified_at' => now(),
        ])->save();

        $activation->update(['used_at' => now()]);

        // Kill any prior sessions, then issue HttpOnly access + refresh cookies.
        $tokens->revokeAll($user);
        $tokens->issuePair($user->fresh(), $request);

        $user = $user->fresh();
        $next = $navigator->nextStep($user);

        return response()->json([
            'user' => [
                ...$user->only('id', 'name', 'email', 'role', 'student_id', 'account_status'),
                'kyc_status' => null,
                'onboarding_next_step' => $next,
                'onboarding_path' => $navigator->frontendPath($next),
            ],
        ]);
    }

    private function findValidToken(string $token): ActivationToken
    {
        $activation = ActivationToken::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (! $activation || $activation->used_at || $activation->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'token' => 'This activation link is invalid or expired.',
            ]);
        }

        return $activation;
    }
}
