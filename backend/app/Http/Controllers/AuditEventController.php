<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditEventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page = max(1, $request->integer('page', 1));
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $search = trim($request->input('search', ''));

        $query = AuditLog::query()->latest();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('actor', 'like', "%{$search}%")
                    ->orWhere('role', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhere('module', 'like', "%{$search}%")
                    ->orWhere('target', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        $total = $query->count();
        $logs = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        return response()->json([
            'data' => $logs,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ]);
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
            'actor' => $user ? $user->name : 'System Developer',
            'role' => $user ? ucfirst($user->role) : 'Developer',
            'action' => $validated['action'],
            'module' => $validated['module'],
            'target' => $validated['target'] ?? null,
            'context' => $validated['context'] ?? null,
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Audit event logged.'], 201);
    }
}
