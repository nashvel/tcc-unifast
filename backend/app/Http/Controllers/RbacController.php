<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\RbacService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RbacController extends Controller
{
    public function __construct(private RbacService $rbac) {}

    public function index(): JsonResponse
    {
        $roles = $this->rbac->getRoleStats();

        return response()->json(['data' => $roles]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:roles,name',
            'description' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:30',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $role = $this->rbac->createRole($validated);

        return response()->json(['data' => $role], 201);
    }

    public function show(Role $role): JsonResponse
    {
        $role->load('permissions');
        return response()->json(['data' => $role]);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:50|unique:roles,name,' . $role->id,
            'description' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:30',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $role = $this->rbac->updateRole($role, $validated);

        return response()->json(['data' => $role]);
    }

    public function destroy(Role $role): JsonResponse
    {
        if ($role->is_system) {
            return response()->json(['message' => 'Cannot delete system roles.'], 403);
        }

        $this->rbac->deleteRole($role);

        return response()->json(['message' => 'Role deleted.']);
    }

    public function permissions(): JsonResponse
    {
        $permissions = $this->rbac->getAllPermissions()->groupBy('category');

        return response()->json(['data' => $permissions]);
    }

    public function storePermission(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:permissions,name',
            'description' => 'nullable|string|max:255',
            'category' => 'required|string|max:50',
        ]);

        $permission = $this->rbac->createPermission($validated);

        return response()->json(['data' => $permission], 201);
    }

    public function destroyPermission(Permission $permission): JsonResponse
    {
        $this->rbac->deletePermission($permission);

        return response()->json(['message' => 'Permission deleted.']);
    }

    public function userRoles(User $user): JsonResponse
    {
        $roles = $this->rbac->getUserRoles($user);

        return response()->json(['data' => $roles]);
    }

    public function assignUserRole(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role_id' => 'required|exists:roles,id',
        ]);

        $role = Role::findOrFail($validated['role_id']);
        $this->rbac->assignRoleToUser($user, $role);

        return response()->json(['message' => 'Role assigned.']);
    }

    public function removeUserRole(User $user, Role $role): JsonResponse
    {
        $this->rbac->removeRoleFromUser($user, $role);

        return response()->json(['message' => 'Role removed.']);
    }

    public function syncUserRoles(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role_ids' => 'required|array',
            'role_ids.*' => 'exists:roles,id',
        ]);

        $this->rbac->syncUserRoles($user, $validated['role_ids']);

        return response()->json(['message' => 'Roles updated.']);
    }

    public function checkPermission(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'permission' => 'required|string',
        ]);

        $hasPermission = $this->rbac->userHasPermission($request->user(), $validated['permission']);

        return response()->json(['has_permission' => $hasPermission]);
    }
}
