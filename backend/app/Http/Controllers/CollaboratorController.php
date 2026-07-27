<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollaboratorController extends Controller
{
    public function index(): JsonResponse
    {
        $collaborators = User::query()
            ->whereIn('role', ['developer', 'admin', 'head', 'staff'])
            ->select(['id', 'name', 'email', 'role', 'account_status', 'created_at'])
            ->get()
            ->map(function ($u) {
                return [
                    'id' => (string) $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $u->role === 'head' ? 'admin' : $u->role,
                    'access' => $u->role === 'developer' ? ['*'] : ['users', 'batches', 'documents', 'settings', 'audit'],
                    'status' => $u->account_status ?? 'active',
                    'invitedAt' => $u->created_at ? $u->created_at->format('M j, Y') : 'Jul 1, 2026',
                ];
            });

        $total = count($collaborators);
        $active = $collaborators->where('status', 'active')->count();
        $pending = $collaborators->where('status', 'pending')->count();
        $developers = $collaborators->where('role', 'developer')->count();

        return response()->json([
            'data' => $collaborators,
            'summary' => [
                'total_members' => $total,
                'active_members' => $active,
                'pending_invites' => $pending,
                'developers' => $developers,
            ],
        ]);
    }

    public function invite(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|unique:users,email',
            'role' => 'required|string|in:developer,admin,staff',
            'access' => 'nullable|array',
        ]);

        $name = explode('@', $validated['email'])[0];
        $user = User::create([
            'name' => ucwords(str_replace('.', ' ', $name)),
            'email' => $validated['email'],
            'password' => bcrypt('password'),
            'role' => $validated['role'],
            'account_status' => 'pending',
        ]);

        $actor = $request->user();
        if ($actor) {
            AuditLog::create([
                'actor' => $actor->name,
                'role' => ucfirst($actor->role),
                'action' => 'collaborator_invite',
                'module' => 'Collaborators',
                'target' => "Invited {$user->email} as {$user->role}",
                'ip_address' => $request->ip(),
            ]);
        }

        return response()->json([
            'data' => [
                'id' => (string) $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'access' => $validated['role'] === 'developer' ? ['*'] : ($validated['access'] ?? []),
                'status' => 'pending',
                'invitedAt' => now()->format('M j, Y'),
            ],
        ], 201);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->role === 'developer' && User::where('role', 'developer')->where('account_status', 'active')->count() <= 1) {
            return response()->json(['message' => 'Cannot deactivate the primary system developer.'], 403);
        }

        $user->update(['account_status' => 'inactive']);

        $actor = $request->user();
        if ($actor) {
            AuditLog::create([
                'actor' => $actor->name,
                'role' => ucfirst($actor->role),
                'action' => 'collaborator_deactivate',
                'module' => 'Collaborators',
                'target' => "Soft-deleted / deactivated collaborator {$user->email}",
                'ip_address' => $request->ip(),
            ]);
        }

        return response()->json(['message' => 'Collaborator deactivated (soft deleted).']);
    }
}
