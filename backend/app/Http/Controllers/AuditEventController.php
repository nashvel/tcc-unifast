<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditEventController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => AuditLog::query()->latest()->limit(250)->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'max:100'],
            'module' => ['required', 'string', 'max:100'],
            'target' => ['nullable', 'string', 'max:255'],
            'context' => ['nullable', 'array'],
        ]);

        $user = $request->user();

        AuditLog::create([
            'actor' => $user->name,
            'role' => ucfirst($user->role),
            'action' => $validated['action'],
            'module' => $validated['module'],
            'target' => $validated['target'] ?? null,
            'context' => $validated['context'] ?? null,
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Audit event logged.'], 201);
    }
}
