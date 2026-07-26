<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RbacService
{
    private const CACHE_TTL = 3600;

    public function getAllRoles(): \Illuminate\Database\Eloquent\Collection
    {
        return Cache::remember('rbac:roles', self::CACHE_TTL, function () {
            return Role::with('permissions')->get();
        });
    }

    public function getAllPermissions(): \Illuminate\Database\Eloquent\Collection
    {
        return Cache::remember('rbac:permissions', self::CACHE_TTL, function () {
            return Permission::all();
        });
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

    public function getUserRoles(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return $user->roles()->get();
    }

    public function getUserPermissions(User $user): \Illuminate\Support\Collection
    {
        $cacheKey = "rbac:user_permissions:{$user->id}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user) {
            return $user->getAllPermissions();
        });
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
        return Cache::remember('rbac:role_stats', self::CACHE_TTL, function () {
            return Role::withCount('users', 'permissions')->get()->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'color' => $role->color,
                'is_system' => $role->is_system,
                'users_count' => $role->users_count,
                'permissions_count' => $role->permissions_count,
                'permissions' => $role->permissions->pluck('name'),
            ])->toArray();
        });
    }

    private function clearCache(): void
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
