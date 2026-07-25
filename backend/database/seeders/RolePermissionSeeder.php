<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'users.read', 'description' => 'View user list and profiles', 'category' => 'Users'],
            ['name' => 'users.write', 'description' => 'Create, edit, and deactivate users', 'category' => 'Users'],
            ['name' => 'batches.read', 'description' => 'View batch list and details', 'category' => 'Batches'],
            ['name' => 'batches.write', 'description' => 'Create and modify batches', 'category' => 'Batches'],
            ['name' => 'documents.read', 'description' => 'View document submissions', 'category' => 'Documents'],
            ['name' => 'documents.write', 'description' => 'Approve or reject submissions', 'category' => 'Documents'],
            ['name' => 'documents.submit', 'description' => 'Upload documents for review', 'category' => 'Documents'],
            ['name' => 'grantees.read', 'description' => 'View grantee list and profiles', 'category' => 'Grantees'],
            ['name' => 'academic.read', 'description' => 'View academic history', 'category' => 'Academic'],
            ['name' => 'settings.read', 'description' => 'View system settings', 'category' => 'Settings'],
            ['name' => 'settings.write', 'description' => 'Modify system settings', 'category' => 'Settings'],
            ['name' => 'audit.read', 'description' => 'View system activity log', 'category' => 'Audit'],
            ['name' => 'profile.read', 'description' => 'View own profile', 'category' => 'Profile'],
            ['name' => 'profile.write', 'description' => 'Update own profile', 'category' => 'Profile'],
            ['name' => 'kyc.submit', 'description' => 'Submit KYC verification', 'category' => 'KYC'],
            ['name' => 'rbac.manage', 'description' => 'Manage roles and permissions', 'category' => 'RBAC'],
        ];

        $permissionModels = [];
        foreach ($permissions as $perm) {
            $permissionModels[$perm['name']] = Permission::updateOrCreate(
                ['name' => $perm['name']],
                $perm
            );
        }

        $roles = [
            [
                'name' => 'developer',
                'description' => 'Full system access including API management and configuration',
                'color' => 'bg-purple-100 text-purple-700',
                'is_system' => true,
                'permissions' => array_keys($permissionModels),
            ],
            [
                'name' => 'admin',
                'description' => 'Manages users, batches, and system settings',
                'color' => 'bg-blue-100 text-blue-700',
                'is_system' => true,
                'permissions' => ['users.read', 'users.write', 'batches.read', 'batches.write', 'settings.read', 'settings.write', 'audit.read', 'rbac.manage'],
            ],
            [
                'name' => 'staff',
                'description' => 'Handles document validation and grantee management',
                'color' => 'bg-green-100 text-green-700',
                'is_system' => true,
                'permissions' => ['grantees.read', 'documents.read', 'documents.write', 'academic.read', 'batches.read'],
            ],
            [
                'name' => 'student',
                'description' => 'Submits documents and views own records',
                'color' => 'bg-orange-100 text-orange-700',
                'is_system' => true,
                'permissions' => ['profile.read', 'profile.write', 'documents.submit', 'kyc.submit'],
            ],
        ];

        foreach ($roles as $roleData) {
            $permissionNames = $roleData['permissions'];
            unset($roleData['permissions']);

            $role = Role::updateOrCreate(
                ['name' => $roleData['name']],
                $roleData
            );

            $rolePermissions = array_map(fn ($name) => $permissionModels[$name]->id, $permissionNames);
            $role->permissions()->sync($rolePermissions);
        }
    }
}
