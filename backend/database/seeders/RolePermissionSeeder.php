<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
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
            ['name' => 'view_database', 'description' => 'View allowlisted database tables', 'category' => 'Developer Tools'],
            // ── Route-level permission slugs (used by permission: middleware) ──
            ['name' => 'view_masterlist', 'description' => 'View and import the grantee masterlist', 'category' => 'Operations'],
            ['name' => 'manage_batches', 'description' => 'Create, activate, deactivate, and extend submission batches', 'category' => 'Operations'],
            ['name' => 'manage_grantees', 'description' => 'Edit grantee records and update statuses', 'category' => 'Operations'],
            ['name' => 'validate_documents', 'description' => 'Approve, reject, or request resubmission of documents', 'category' => 'Validation'],
            ['name' => 'review_academics', 'description' => 'Access academic records and grade breakdowns', 'category' => 'Validation'],
            ['name' => 'run_eligibility', 'description' => 'Run eligibility checks and send eligibility notices', 'category' => 'Validation'],
            ['name' => 'publish_announcements', 'description' => 'Create and publish announcements to students', 'category' => 'Communication'],
            ['name' => 'generate_reports', 'description' => 'Generate and download billing and distribution reports', 'category' => 'Communication'],
            ['name' => 'manage_support', 'description' => 'View and respond to support tickets', 'category' => 'Communication'],
            ['name' => 'view_audit_trail', 'description' => 'Access the full system audit log', 'category' => 'Administration'],
            ['name' => 'manage_users', 'description' => 'Invite, deactivate, and manage staff accounts', 'category' => 'Administration'],
            ['name' => 'change_settings', 'description' => 'Modify policy and system settings', 'category' => 'Administration'],
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
                'permissions' => array_keys($permissionModels), // all permissions
            ],
            [
                'name' => 'admin',
                'description' => 'Manages users, batches, and system settings',
                'color' => 'bg-blue-100 text-blue-700',
                'is_system' => true,
                'permissions' => [
                    'users.read', 'users.write', 'batches.read', 'batches.write',
                    'settings.read', 'settings.write', 'audit.read', 'rbac.manage',
                    // Route-level slugs
                    'view_masterlist', 'manage_batches', 'manage_grantees',
                    'validate_documents', 'review_academics', 'run_eligibility',
                    'publish_announcements', 'generate_reports', 'manage_support',
                    'view_audit_trail', 'manage_users', 'change_settings',
                ],
            ],
            [
                'name' => 'staff',
                'description' => 'Handles document validation and grantee management',
                'color' => 'bg-green-100 text-green-700',
                'is_system' => true,
                'permissions' => [
                    'grantees.read', 'documents.read', 'documents.write', 'academic.read', 'batches.read',
                    // Route-level slugs — staff gets validation and operations, not admin tasks
                    'view_masterlist', 'manage_batches', 'manage_grantees',
                    'validate_documents', 'review_academics', 'run_eligibility',
                ],
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
