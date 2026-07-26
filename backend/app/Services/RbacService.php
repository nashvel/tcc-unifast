<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Cache;

class RbacService
{
    private const CACHE_TTL = 3600; // 1 hour

    public function getAllRoles(): EloquentCollection
    {
        $roles = Cache::get('rbac:roles');

        if (!$roles instanceof EloquentCollection) {
            Cache::forget('rbac:roles');
            $roles = Role::with('permissions')->get();
            try {
                Cache::put('rbac:roles', $roles, self::CACHE_TTL);
            } catch (\Throwable $e) {
                // Ignore cache write errors
            }
        }

        return $roles;
    }

    public function getAllPermissions(): EloquentCollection
    {
        $permissions = Cache::get('rbac:permissions');

        if (!$permissions instanceof EloquentCollection) {
            Cache::forget('rbac:permissions');
            $permissions = Permission::all();
            try {
                Cache::put('rbac:permissions', $permissions, self::CACHE_TTL);
            } catch (\Throwable $e) {
                // Ignore cache write errors
            }
        }

        return $permissions;
    }

    public function getRoleById(int $id): ?Role
    {
        return Role::with('permissions')->find($id);
    }

    public function createRole(array $data): Role
    {
        $role = Role::create($data);

        if (!empty($data['permission_ids'])) {
            $role->permissions()->sync($data['permission_ids']);
        }

        $this->clearCache();

        return $role->load('permissions');
    }

    public function updateRole(Role $role, array $data): Role
    {
        $role->update($data);

        if (array_key_exists('permission_ids', $data)) {
            $role->permissions()->sync($data['permission_ids'] ?? []);
        }

        $this->clearCache();

        return $role->load('permissions');
    }

    public function deleteRole(Role $role): bool
    {
        if ($role->is_system) {
            return false;
        }

        $role->users()->detach();
        $role->permissions()->detach();
        $role->delete();

        $this->clearCache();

        return true;
    }

    public function assignRoleToUser(User $user, Role $role): void
    {
        if (!$user->roles->contains($role->id)) {
            $user->roles()->attach($role);
            $this->clearUserCache($user);
        }
    }

    public function removeRoleFromUser(User $user, Role $role): void
    {
        $user->roles()->detach($role);
        $this->clearUserCache($user);
    }

    public function syncUserRoles(User $user, array $roleIds): void
    {
        $user->roles()->sync($roleIds);
        $this->clearUserCache($user);
    }

    public function getUserRoles(User $user): EloquentCollection
    {
        return $user->roles()->get();
    }

    public function getUserPermissions(User $user): SupportCollection
    {
        $cacheKey = "rbac:user_permissions:{$user->id}";
        $permissions = Cache::get($cacheKey);

        if (!$permissions instanceof SupportCollection) {
            Cache::forget($cacheKey);
            $permissions = $user->getAllPermissions();
            try {
                Cache::put($cacheKey, $permissions, self::CACHE_TTL);
            } catch (\Throwable $e) {
                // Ignore cache write errors
            }
        }

        return $permissions;
    }

    public function userHasPermission(User $user, string $permissionName): bool
    {
        return $this->getUserPermissions($user)->contains('name', $permissionName);
    }

    public function userHasAnyPermission(User $user, array $permissionNames): bool
    {
        return $this->getUserPermissions($user)->whereIn('name', $permissionNames)->isNotEmpty();
    }

    public function createPermission(array $data): Permission
    {
        $permission = Permission::create($data);
        $this->clearCache();
        return $permission;
    }

    public function updatePermission(Permission $permission, array $data): Permission
    {
        $permission->update($data);
        $this->clearCache();
        return $permission;
    }

    public function deletePermission(Permission $permission): bool
    {
        $permission->roles()->detach();
        $permission->delete();
        $this->clearCache();
        return true;
    }

    public function getRoleStats(): array
    {
        Cache::forget('rbac:role_stats');

        $stats = Role::withCount('users', 'permissions')->get()->map(fn ($role) => [
            'id' => $role->id,
            'name' => $role->name,
            'description' => $role->description,
            'color' => $role->color,
            'is_system' => $role->is_system,
            'users_count' => $role->users_count,
            'permissions_count' => $role->permissions_count,
            'permissions' => ($role->is_system && strtolower($role->name) === 'developer')
                ? ['*']
                : $role->permissions->pluck('name')->values()->all(),
        ])->toArray();

        try {
            Cache::put('rbac:role_stats', $stats, self::CACHE_TTL);
        } catch (\Throwable $e) {
            // Ignore cache write errors
        }

        return $stats;
    }

    public function clearCache(): void
    {
        Cache::forget('rbac:roles');
        Cache::forget('rbac:permissions');
        Cache::forget('rbac:role_stats');
    }

    private function clearUserCache(User $user): void
    {
        Cache::forget("rbac:user_permissions:{$user->id}");
    }
}
