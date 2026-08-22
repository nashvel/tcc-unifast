<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class StudentSettingsController extends Controller
{
    public function updateSecurityPin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'pin' => ['nullable', 'string', 'min:4', 'max:6'],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'The provided password does not match our records.',
            ]);
        }

        $pin = $validated['pin'] ?? null;

        $user->update([
            'security_pin' => $pin ? Hash::make($pin) : null,
        ]);

        return response()->json([
            'message' => $pin ? 'Security PIN configured successfully.' : 'Security PIN removed.',
        ]);
    }
}
