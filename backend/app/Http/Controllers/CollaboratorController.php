<?php

namespace App\Http\Controllers;

use App\Mail\StaffInviteMail;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivationTokenIssuer;
use App\Support\ActivationLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CollaboratorController extends Controller
{
    public function index(): JsonResponse
    {
        $collaborators = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['developer', 'admin', 'staff']))
            ->with(['roles.permissions:id,name'])
            ->select(['id', 'name', 'email', 'account_status', 'created_at'])
            ->get()
            ->map(function ($u) {
                $role = $u->roles->first();

                return [
                    'id' => (string) $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $role?->name,
                    'access' => $role?->name === 'developer' ? ['*'] : $role?->permissions->pluck('name')->values()->all(),
                    'status' => $u->account_status ?? 'active',
                    'invitedAt' => $u->created_at?->toDateString(),
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

    public function invite(Request $request, ActivationTokenIssuer $issuer): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|unique:users,email',
            'role' => 'required|string|in:developer,admin,staff',
            'access' => 'nullable|array',
        ]);

        $actor = $request->user();
        if ($validated['role'] === 'developer' && ! $actor?->hasRole('developer')) {
            return response()->json([
                'message' => 'Only developers can invite developer accounts.',
            ], 403);
        }

        $name = explode('@', $validated['email'])[0];

        // Unusable random hash — never a shared literal. Previously this was
        // bcrypt('password'), which meant anyone able to invite could mint a
        // developer account whose credential was publicly guessable.
        $user = User::create([
            'name' => ucwords(str_replace('.', ' ', $name)),
            'email' => $validated['email'],
            'password' => Hash::make(Str::random(64)),
            'role' => $validated['role'],
            'account_status' => 'pending',
        ]);
        $user->roles()->attach(Role::query()->where('name', $validated['role'])->firstOrFail());

        // The invite link is the sole proof of invitation; the collaborator sets
        // their own password via StaffActivationController.
        $link = $issuer->issueLinkFor($user);
        $actor = $request->user();
        $mailed = true;

        try {
            Mail::to($user->email, $user->name)->send(
                new StaffInviteMail($user, $this->staffActivationUrl($link['token']), $actor?->name),
            );
        } catch (\Throwable $exception) {
            report($exception);
            $mailed = false;
        }

        if ($actor) {
            AuditLog::create([
                'actor' => $actor->name,
                'role' => $actor->roles()->value('name') ?? 'Unknown',
                'action' => 'collaborator_invite',
                'module' => 'Collaborators',
                'target' => "Invited {$user->email} as {$user->role}",
                'context' => ['invite_link_sent' => $mailed],
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
                'invitedAt' => now()->toDateString(),
                'invite_link_sent' => $mailed,
            ],
        ], 201);
    }

    private function staffActivationUrl(string $plainToken): string
    {
        return ActivationLink::base().'/staff-activate/'.$plainToken.'?lang=en';
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->hasRole('developer') && User::query()
            ->where('account_status', 'active')
            ->whereHas('roles', fn ($query) => $query->where('name', 'developer'))
            ->count() <= 1) {
            return response()->json(['message' => 'Cannot deactivate the primary system developer.'], 403);
        }

        $user->update(['account_status' => 'inactive']);

        $actor = $request->user();
        if ($actor) {
            AuditLog::create([
                'actor' => $actor->name,
                'role' => $actor->roles()->value('name') ?? 'Unknown',
                'action' => 'collaborator_deactivate',
                'module' => 'Collaborators',
                'target' => "Soft-deleted / deactivated collaborator {$user->email}",
                'ip_address' => $request->ip(),
            ]);
        }

        return response()->json(['message' => 'Collaborator deactivated (soft deleted).']);
    }

    public function reactivate(Request $request, User $user): JsonResponse
    {
        $user->update(['account_status' => 'active']);

        if ($actor = $request->user()) {
            AuditLog::create([
                'actor' => $actor->name,
                'role' => $actor->roles()->value('name') ?? 'Unknown',
                'action' => 'collaborator_reactivate',
                'module' => 'Collaborators',
                'target' => "Reactivated collaborator {$user->email}",
                'ip_address' => $request->ip(),
            ]);
        }

        return response()->json(['data' => [
            'id' => (string) $user->id,
            'status' => $user->account_status,
        ]]);
    }
}
