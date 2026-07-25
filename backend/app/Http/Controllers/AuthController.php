<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);

        if (! Auth::attempt($credentials)) {
            return response()->json(['message' => 'The email or password is incorrect.'], 422);
        }

        $user = $request->user();
        $token = $user->createToken('auth-token')->plainTextToken;

        AuditLog::create([
            'actor' => $user->name,
            'role' => ucfirst($user->role),
            'action' => 'auth_login',
            'module' => 'Authentication',
            'target' => $user->email,
            'context' => ['method' => 'sanctum_token'],
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'user' => $this->presentUser($user),
            'token' => $token,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return $request->user()
            ? response()->json(['user' => $this->presentUser($request->user())])
            : response()->json(['message' => 'Unauthenticated.'], 401);
    }

    public function logout(Request $request): JsonResponse
    {
        if ($request->user()) {
            AuditLog::create([
                'actor' => $request->user()->name,
                'role' => ucfirst($request->user()->role),
                'action' => 'auth_logout',
                'module' => 'Authentication',
                'target' => $request->user()->email,
                'context' => ['method' => 'sanctum_token'],
                'ip_address' => $request->ip(),
            ]);

            $request->user()->currentAccessToken()->delete();
        }

        return response()->json(['message' => 'Signed out.']);
    }

    private function presentUser(\App\Models\User $user): array
    {
        $user->loadMissing('kycProfile');

        return [
            ...$user->only('id', 'name', 'email', 'role', 'student_id', 'account_status'),
            'kyc_status' => $user->kycProfile?->status,
        ];
    }
}
