<?php

namespace App\Http\Controllers;

use App\Models\ActivationToken;
use App\Models\AuditLog;
use App\Services\AuthTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Activation for staff / admin / developer collaborators.
 *
 * These accounts do not pass through the biometric funnel — there is no identity
 * check to wait for — so the password IS set from the link. Ownership rests on an
 * administrator having authorised the invite (§2.4).
 *
 * Kept separate from ActivationController so the student funnel's "no credential
 * before verification" invariant cannot be bypassed by pointing a student token at
 * a staff endpoint: the role is asserted here.
 */
class StaffActivationController extends Controller
{
    /** @var list<string> */
    private const STAFF_ROLES = ['developer', 'admin', 'head', 'staff'];

    public function show(string $token): JsonResponse
    {
        $activation = $this->findStaffToken($token);
        $user = $activation->user()->firstOrFail();

        return response()->json([
            'data' => [
                'valid' => true,
                'email' => $user->email,
                'role' => $user->role,
                'expires_at' => $activation->expires_at,
            ],
        ]);
    }

    public function activate(Request $request, string $token, AuthTokenService $tokens): JsonResponse
    {
        $activation = $this->findStaffToken($token);
        $user = $activation->user()->firstOrFail();

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'account_status' => 'active',
            'activated_at' => now(),
            'email_verified_at' => now(),
        ])->save();

        $activation->update(['used_at' => now()]);

        $tokens->revokeAll($user);
        $tokens->issuePair($user->fresh(), $request);

        $user = $user->fresh();

        AuditLog::create([
            'actor' => $user->name,
            'role' => ucfirst((string) $user->role),
            'action' => 'staff_invite_accepted',
            'module' => 'Collaborators',
            'target' => "User #{$user->id}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'user' => $user->only('id', 'name', 'email', 'role', 'account_status'),
            'message' => 'Account activated.',
        ]);
    }

    private function findStaffToken(string $token): ActivationToken
    {
        $activation = ActivationToken::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (! $activation || ! $activation->isUsable()) {
            throw ValidationException::withMessages([
                'token' => 'This invitation link is invalid or expired. Ask an administrator to resend it.',
            ]);
        }

        // A student token must never reach this endpoint — that would set a password
        // without any identity verification.
        $role = (string) $activation->user()->value('role');
        if (! in_array($role, self::STAFF_ROLES, true)) {
            throw ValidationException::withMessages([
                'token' => 'This invitation link is invalid or expired. Ask an administrator to resend it.',
            ]);
        }

        return $activation;
    }
}
