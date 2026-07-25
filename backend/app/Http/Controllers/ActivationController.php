<?php

namespace App\Http\Controllers;

use App\Models\ActivationToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

    public function activate(Request $request, string $token): JsonResponse
    {
        $validated = $request->validate([
            'temporary_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $activation = $this->findValidToken($token);
        $user = $activation->user()->firstOrFail();

        if (! Hash::check($validated['temporary_password'], $user->password)) {
            throw ValidationException::withMessages([
                'temporary_password' => 'The temporary password is incorrect.',
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'account_status' => 'pending_kyc',
            'activated_at' => now(),
            'email_verified_at' => now(),
        ])->save();

        $activation->update(['used_at' => now()]);
        Auth::login($user);
        $request->session()->regenerate();

        return response()->json([
            'user' => [
                ...$user->only('id', 'name', 'email', 'role', 'student_id', 'account_status'),
                'kyc_status' => null,
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
