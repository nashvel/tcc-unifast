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

    private function resolvePermissionIds(array $inputs): array
    {
        $resolvedIds = [];
        foreach ($inputs as $item) {
            if (is_numeric($item)) {
                $perm = Permission::find((int) $item);
                if ($perm) {
                    $resolvedIds[] = $perm->id;
                }
            } elseif (is_string($item) && trim($item) !== '') {
                // Only accept slugs that already exist in the permissions table.
                // Never auto-create permissions via a role update — that would let
                // admins invent arbitrary permission slugs and pollute the RBAC table.
                $perm = Permission::where('name', trim($item))->first();
                if ($perm) {
                    $resolvedIds[] = $perm->id;
                }
                // Unknown string slugs are silently ignored.
            }
        }

        return array_values(array_unique($resolvedIds));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:roles,name',
            'description' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:30',
            'permission_ids' => 'nullable|array',
        ]);

        if (! empty($validated['permission_ids'])) {
            $validated['permission_ids'] = $this->resolvePermissionIds($validated['permission_ids']);
        }

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
        // Strictly lock permissions on developer (root superuser) and student (grantee portal).
        // Developer permissions are full system wildcard; student permissions are strictly portal-bound.
        $lowerRoleName = strtolower($role->name);
        if (in_array($lowerRoleName, ['developer', 'student'], true) && array_key_exists('permission_ids', $request->all())) {
            return response()->json([
                'message' => "Permissions on the {$role->name} role are fixed and cannot be modified.",
            ], 403);
        }

        // Prevent renaming seeded system roles.
        if ($role->is_system && $request->has('name') && $request->input('name') !== $role->name) {
            return response()->json([
                'message' => 'System role names cannot be modified.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:50|unique:roles,name,'.$role->id,
            'description' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:30',
            'permission_ids' => 'nullable|array',
        ]);

        if (array_key_exists('permission_ids', $validated) && is_array($validated['permission_ids'])) {
            $resolved = $this->resolvePermissionIds($validated['permission_ids']);

            // Developer-only permissions (rbac.manage, view_database) must NOT be assignable to operational roles.
            if ($lowerRoleName !== 'developer') {
                $devOnlyPerms = Permission::whereIn('name', ['rbac.manage', 'view_database'])->pluck('id')->all();
                if (! empty(array_intersect($resolved, $devOnlyPerms))) {
                    return response()->json([
                        'message' => 'Developer-only permissions (rbac.manage, view_database) cannot be assigned to operational roles.',
                    ], 403);
                }
            }

            // Grantee-only permissions (documents.submit, kyc.submit, profile) must NOT be assignable to staff or admin roles.
            if ($lowerRoleName !== 'student') {
                $granteeOnlyPerms = Permission::whereIn('name', ['documents.submit', 'kyc.submit', 'profile.read', 'profile.write'])->pluck('id')->all();
                if (! empty(array_intersect($resolved, $granteeOnlyPerms))) {
                    return response()->json([
                        'message' => 'Grantee-only permissions cannot be assigned to operational staff or admin roles.',
                    ], 403);
                }
            }

            $validated['permission_ids'] = $resolved;
        }

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

    public function permissions(Request $request): JsonResponse
    {
        $allPermissions = $this->rbac->getAllPermissions();

        if ($request->query('scope') === 'operational') {
            $nonAssignable = [
                'rbac.manage',
                'view_database',
                'documents.submit',
                'kyc.submit',
                'profile.read',
                'profile.write',
            ];
            $allPermissions = $allPermissions->whereNotIn('name', $nonAssignable)->values();
        }

        $permissions = $allPermissions->groupBy('category');

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
        // Developers cannot have roles assigned or changed — permanent root superusers
        if ($user->role === 'developer') {
            return response()->json([
                'message' => 'Developer accounts have permanent root superuser access and cannot have roles modified.',
            ], 403);
        }

        // Students/grantees cannot be assigned operational staff/admin roles
        if ($user->role === 'student') {
            return response()->json([
                'message' => 'Grantees cannot be assigned operational staff or admin roles.',
            ], 403);
        }

        $validated = $request->validate([
            'role_id' => 'required|exists:roles,id',
        ]);

        $role = Role::findOrFail($validated['role_id']);

        // Developer role cannot be assigned
        if ($role->name === 'developer') {
            return response()->json([
                'message' => 'The developer role is a permanent root superuser and cannot be assigned.',
            ], 403);
        }

        // Student role cannot be assigned to staff or admin
        if ($role->name === 'student') {
            return response()->json([
                'message' => 'The student/grantee role cannot be assigned to operational staff or admin accounts.',
            ], 403);
        }

        $this->rbac->assignRoleToUser($user, $role);

        return response()->json(['message' => 'Role assigned.']);
    }

    public function removeUserRole(User $user, Role $role): JsonResponse
    {
        if ($user->role === 'developer') {
            return response()->json([
                'message' => 'Developer accounts have permanent root superuser access and cannot have roles modified.',
            ], 403);
        }

        $this->rbac->removeRoleFromUser($user, $role);

        return response()->json(['message' => 'Role removed.']);
    }

    public function syncUserRoles(Request $request, User $user): JsonResponse
    {
        if ($user->role === 'developer') {
            return response()->json([
                'message' => 'Developer accounts have permanent root superuser access and cannot have roles modified.',
            ], 403);
        }

        if ($user->role === 'student') {
            return response()->json([
                'message' => 'Grantees cannot be assigned operational staff or admin roles.',
            ], 403);
        }

        $validated = $request->validate([
            'role_ids' => 'required|array',
        ]);

        $devRole = Role::where('name', 'developer')->first();
        $studentRole = Role::where('name', 'student')->first();

        $resolvedRoleIds = [];
        foreach ($validated['role_ids'] as $rId) {
            if ($devRole && (int) $rId === (int) $devRole->id) {
                return response()->json([
                    'message' => 'The developer role is a permanent root superuser and cannot be assigned.',
                ], 403);
            }
            if ($studentRole && (int) $rId === (int) $studentRole->id) {
                return response()->json([
                    'message' => 'The student/grantee role cannot be assigned to operational staff or admin accounts.',
                ], 403);
            }
            if (is_numeric($rId) && Role::where('id', (int) $rId)->exists()) {
                $resolvedRoleIds[] = (int) $rId;
            }
        }

        $this->rbac->syncUserRoles($user, $resolvedRoleIds);

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

    public const OPERATIONAL_MODULES = [
        'masterlist' => [
            'key' => 'masterlist',
            'name' => 'Grantee Masterlist',
            'desk' => 'Operations Desk',
            'description' => 'View, filter, and import the grantee masterlist',
            'permissions' => ['view_masterlist', 'grantees.read', 'masterlist.read', 'masterlist.write'],
        ],
        'batches' => [
            'key' => 'batches',
            'name' => 'Batch Management',
            'desk' => 'Operations Desk',
            'description' => 'Create, activate, deactivate, and extend submission batches',
            'permissions' => ['manage_batches', 'batches.read', 'batches.write'],
        ],
        'grantees' => [
            'key' => 'grantees',
            'name' => 'Grantee Registry Management',
            'desk' => 'Operations Desk',
            'description' => 'Administrative management of grantee profiles, academic status, and eligibility audits (staff desk)',
            'permissions' => ['manage_grantees', 'grantees.read', 'grantees.write'],
        ],
        'documents' => [
            'key' => 'documents',
            'name' => 'Document Vault Review',
            'desk' => 'Validation Desk',
            'description' => 'Approve, reject, or request resubmission of required student files',
            'permissions' => ['validate_documents', 'documents.read', 'documents.write'],
        ],
        'academic' => [
            'key' => 'academic',
            'name' => 'Academic Records',
            'desk' => 'Validation Desk',
            'description' => 'Access academic history, TOR, and grade breakdowns',
            'permissions' => ['review_academics', 'academic.read', 'academic.write'],
        ],
        'eligibility' => [
            'key' => 'eligibility',
            'name' => 'Eligibility Verification',
            'desk' => 'Validation Desk',
            'description' => 'Run automated eligibility evaluation rules and send notices',
            'permissions' => ['run_eligibility', 'eligibility.read', 'eligibility.write'],
        ],
        'announcements' => [
            'key' => 'announcements',
            'name' => 'Announcements Desk',
            'desk' => 'Communication & Reports',
            'description' => 'Create and publish institutional announcements to students',
            'permissions' => ['publish_announcements', 'announcements.read', 'announcements.write'],
        ],
        'reports' => [
            'key' => 'reports',
            'name' => 'Billing & Distribution Reports',
            'desk' => 'Communication & Reports',
            'description' => 'Generate UniFAST billing submissions and track disbursements',
            'permissions' => ['generate_reports', 'reports.read', 'reports.write'],
        ],
        'support' => [
            'key' => 'support',
            'name' => 'Support Tickets Desk',
            'desk' => 'Communication & Reports',
            'description' => 'View and respond to student inquiries and support tickets',
            'permissions' => ['manage_support', 'support.read', 'support.write'],
        ],
        'users' => [
            'key' => 'users',
            'name' => 'Staff Users & Access',
            'desk' => 'Administration & Security',
            'description' => 'Manage staff accounts and operational permission matrix',
            'permissions' => ['manage_users', 'users.read', 'users.write'],
        ],
        'audit' => [
            'key' => 'audit',
            'name' => 'System Audit Trail',
            'desk' => 'Administration & Security',
            'description' => 'Inspect security events and audit activity logs',
            'permissions' => ['view_audit_trail', 'audit.read'],
        ],
        'settings' => [
            'key' => 'settings',
            'name' => 'Global System Settings',
            'desk' => 'Administration & Security',
            'description' => 'Configure academic year, program requirements, and system policies',
            'permissions' => ['change_settings', 'settings.read', 'settings.write'],
        ],
    ];

    public function getUserAssignedModules(User $user): array
    {
        if ($user->role === 'developer') {
            return array_keys(self::OPERATIONAL_MODULES);
        }

        $userPerms = $user->getAllPermissions()->pluck('name')->all();

        $primarySlugs = [
            'masterlist' => 'view_masterlist',
            'batches' => 'manage_batches',
            'grantees' => 'manage_grantees',
            'documents' => 'validate_documents',
            'academic' => 'review_academics',
            'eligibility' => 'run_eligibility',
            'announcements' => 'publish_announcements',
            'reports' => 'generate_reports',
            'support' => 'manage_support',
            'users' => 'manage_users',
            'audit' => 'view_audit_trail',
            'settings' => 'change_settings',
        ];

        $assigned = [];
        foreach (self::OPERATIONAL_MODULES as $modKey => $mod) {
            $primary = $primarySlugs[$modKey] ?? $mod['permissions'][0] ?? null;
            if ($primary && in_array($primary, $userPerms, true)) {
                $assigned[] = $modKey;
            } elseif (empty(array_diff($mod['permissions'], $userPerms))) {
                $assigned[] = $modKey;
            }
        }

        return $assigned;
    }

    public function userModules(): JsonResponse
    {
        $modules = array_values(self::OPERATIONAL_MODULES);

        // Fetch staff and admin users for dynamic assignment
        $allUsers = User::query()
            ->whereIn('role', ['developer', 'admin', 'head', 'staff'])
            ->select(['id', 'name', 'email', 'role'])
            ->get();

        // Assignable columns in the matrix: Staff & Admin ONLY. Developers cannot be assigned.
        $assignableUsers = $allUsers
            ->filter(fn ($u) => $u->role !== 'developer')
            ->map(function ($u) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $u->role === 'head' ? 'admin' : $u->role,
                    'is_developer' => false,
                    'is_assignable' => true,
                    'assigned_modules' => $this->getUserAssignedModules($u),
                ];
            })
            ->values();

        // Developer superusers: returned separately for system transparency / status card
        $developerUsers = $allUsers
            ->filter(fn ($u) => $u->role === 'developer')
            ->map(function ($u) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => 'developer',
                    'is_developer' => true,
                    'is_assignable' => false,
                    'assigned_modules' => array_keys(self::OPERATIONAL_MODULES),
                ];
            })
            ->values();

        return response()->json([
            'data' => [
                'modules' => $modules,
                'users' => $assignableUsers,
                'developers' => $developerUsers,
                'non_assignable' => [
                    'developer_modules' => [
                        [
                            'key' => 'dev_rbac',
                            'name' => 'RBAC Engine & Permission Manager',
                            'description' => 'System security controls and core access rule management',
                            'reason' => 'Restricted to System Developers only; cannot be assigned to staff or admin.',
                        ],
                        [
                            'key' => 'dev_api',
                            'name' => 'Developer API Docs & OpenSpecs',
                            'description' => 'Interactive Swagger / OpenAPI endpoints and contract specifications',
                            'reason' => 'Restricted to System Developers only; cannot be assigned to staff or admin.',
                        ],
                        [
                            'key' => 'dev_flowchart',
                            'name' => 'Architecture & Execution Flowchart',
                            'description' => 'System execution flow, middleware pipelines, and telemetry',
                            'reason' => 'Restricted to System Developers only; cannot be assigned to staff or admin.',
                        ],
                        [
                            'key' => 'dev_database',
                            'name' => 'Direct Database Table Viewer',
                            'description' => 'Low-level inspect of allowlisted raw database tables',
                            'reason' => 'Restricted to System Developers only; cannot be assigned to staff or admin.',
                        ],
                        [
                            'key' => 'dev_services',
                            'name' => 'Background Services & Telemetry',
                            'description' => 'Cloudflare tunnel daemon and OCR background workers',
                            'reason' => 'Restricted to System Developers only; cannot be assigned to staff or admin.',
                        ],
                    ],
                    'grantee_modules' => [
                        [
                            'key' => 'student_kyc',
                            'name' => 'Student KYC & Biometric Face Scan',
                            'description' => 'Student identity verification and liveness check funnel',
                            'reason' => 'Exclusive to student accounts; cannot be assigned to staff or admin.',
                        ],
                        [
                            'key' => 'student_vault',
                            'name' => 'Requirement Vault Document Upload',
                            'description' => 'Student document submission slots and re-upload workflows',
                            'reason' => 'Exclusive to student accounts; cannot be assigned to staff or admin.',
                        ],
                        [
                            'key' => 'student_forms',
                            'name' => 'Student Dynamic Forms Portal',
                            'description' => 'Student responding to assigned survey and scholarship forms',
                            'reason' => 'Exclusive to student accounts; cannot be assigned to staff or admin.',
                        ],
                        [
                            'key' => 'student_profile',
                            'name' => 'Student Profile & Security PIN',
                            'description' => 'Student personal record and self-service PIN management',
                            'reason' => 'Exclusive to student accounts; cannot be assigned to staff or admin.',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function updateUserModules(Request $request, User $user): JsonResponse
    {
        // Developers cannot be assigned or modified — permanent superusers
        if ($user->role === 'developer') {
            return response()->json([
                'message' => 'Developer accounts have permanent root superuser access and cannot be modified.',
            ], 403);
        }

        // Grantees (students) cannot be assigned staff operational modules
        if ($user->role === 'student') {
            return response()->json([
                'message' => 'Grantees cannot be assigned operational staff modules.',
            ], 403);
        }

        $validated = $request->validate([
            'module_key' => 'nullable|string',
            'enabled' => 'nullable|boolean',
            'modules' => 'nullable|array',
            'modules.*' => 'string',
        ]);

        $targetModules = [];
        if ($request->has('modules')) {
            $targetModules = (array) $request->input('modules', []);
        } elseif ($request->has('module_key')) {
            $current = $this->getUserAssignedModules($user);
            $key = (string) $request->input('module_key');
            $enabled = (bool) $request->input('enabled', true);

            if ($enabled && ! in_array($key, $current, true)) {
                $current[] = $key;
            } elseif (! $enabled) {
                $current = array_values(array_filter($current, fn ($m) => $m !== $key));
            }
            $targetModules = $current;
        } else {
            return response()->json([
                'message' => 'Please provide either modules array or module_key and enabled state.',
            ], 422);
        }

        // Guard: Reject non-operational / developer / grantee module keys
        $invalidKeys = array_diff($targetModules, array_keys(self::OPERATIONAL_MODULES));
        if (! empty($invalidKeys)) {
            return response()->json([
                'message' => 'Invalid operational module key(s): '.implode(', ', $invalidKeys),
            ], 422);
        }

        // Collect permissions associated with target modules
        $permSlugs = [];
        foreach ($targetModules as $modKey) {
            $permSlugs = array_merge($permSlugs, self::OPERATIONAL_MODULES[$modKey]['permissions']);
        }
        $permSlugs = array_values(array_unique($permSlugs));

        $permIds = Permission::whereIn('name', $permSlugs)->pluck('id')->all();

        // Sync to user direct permissions in permission_user table
        $user->permissions()->sync($permIds);

        // Clear user permission cache
        \Illuminate\Support\Facades\Cache::forget("rbac:user_permissions:{$user->id}");

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role === 'head' ? 'admin' : $user->role,
                'is_developer' => false,
                'is_assignable' => true,
                'assigned_modules' => $this->getUserAssignedModules($user),
            ],
            'message' => "Module permissions updated for {$user->name}.",
        ]);
    }
}
