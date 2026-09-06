<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicRbacMatrixTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private Role $adminRole;
    private Role $staffRole;
    private Role $developerRole;
    private Role $studentRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->adminRole = Role::where('name', 'admin')->firstOrFail();
        $this->staffRole = Role::where('name', 'staff')->firstOrFail();
        $this->developerRole = Role::where('name', 'developer')->firstOrFail();
        $this->studentRole = Role::where('name', 'student')->firstOrFail();

        $this->adminUser = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);
        $this->adminUser->roles()->attach($this->adminRole);
    }

    public function test_staff_role_can_have_operational_permissions_dynamically_updated(): void
    {
        $newPerms = ['view_masterlist', 'manage_batches', 'validate_documents'];

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/rbac/roles/{$this->staffRole->id}", [
                'permission_ids' => $newPerms,
            ]);

        $response->assertOk();

        $this->staffRole->refresh();
        $actualPerms = $this->staffRole->permissions->pluck('name')->all();

        sort($newPerms);
        sort($actualPerms);
        $this->assertEquals($newPerms, $actualPerms);
    }

    public function test_admin_role_can_have_operational_permissions_dynamically_updated(): void
    {
        $newPerms = ['view_masterlist', 'manage_batches', 'generate_reports', 'manage_users'];

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/rbac/roles/{$this->adminRole->id}", [
                'permission_ids' => $newPerms,
            ]);

        $response->assertOk();

        $this->adminRole->refresh();
        $actualPerms = $this->adminRole->permissions->pluck('name')->all();

        sort($newPerms);
        sort($actualPerms);
        $this->assertEquals($newPerms, $actualPerms);
    }

    public function test_developer_role_permissions_cannot_be_modified(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/rbac/roles/{$this->developerRole->id}", [
                'permission_ids' => ['view_masterlist'],
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Permissions on the developer role are fixed and cannot be modified.');
    }

    public function test_student_role_permissions_cannot_be_modified(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/rbac/roles/{$this->studentRole->id}", [
                'permission_ids' => ['view_masterlist'],
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Permissions on the student role are fixed and cannot be modified.');
    }

    public function test_developer_only_permissions_cannot_be_assigned_to_staff_or_admin(): void
    {
        // Try assigning rbac.manage
        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/rbac/roles/{$this->staffRole->id}", [
                'permission_ids' => ['view_masterlist', 'rbac.manage'],
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Developer-only permissions (rbac.manage, view_database) cannot be assigned to operational roles.');

        // Try assigning view_database
        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/rbac/roles/{$this->adminRole->id}", [
                'permission_ids' => ['view_masterlist', 'view_database'],
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Developer-only permissions (rbac.manage, view_database) cannot be assigned to operational roles.');
    }

    public function test_grantee_only_permissions_cannot_be_assigned_to_staff_or_admin(): void
    {
        // Try assigning kyc.submit
        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/rbac/roles/{$this->staffRole->id}", [
                'permission_ids' => ['view_masterlist', 'kyc.submit'],
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Grantee-only permissions cannot be assigned to operational staff or admin roles.');

        // Try assigning documents.submit
        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/rbac/roles/{$this->adminRole->id}", [
                'permission_ids' => ['view_masterlist', 'documents.submit'],
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Grantee-only permissions cannot be assigned to operational staff or admin roles.');
    }

    public function test_operational_permissions_scope_filters_out_developer_and_grantee_modules(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/rbac/permissions?scope=operational');

        $response->assertOk();

        $data = $response->json('data');
        $allReturnedSlugs = [];
        foreach ($data as $category => $perms) {
            foreach ($perms as $p) {
                $allReturnedSlugs[] = $p['name'];
            }
        }

        // Must NOT contain developer-only slugs
        $this->assertNotContains('rbac.manage', $allReturnedSlugs);
        $this->assertNotContains('view_database', $allReturnedSlugs);

        // Must NOT contain grantee-only slugs
        $this->assertNotContains('kyc.submit', $allReturnedSlugs);
        $this->assertNotContains('documents.submit', $allReturnedSlugs);
        $this->assertNotContains('profile.read', $allReturnedSlugs);
        $this->assertNotContains('profile.write', $allReturnedSlugs);

        // MUST contain operational slugs
        $this->assertContains('view_masterlist', $allReturnedSlugs);
        $this->assertContains('manage_batches', $allReturnedSlugs);
        $this->assertContains('validate_documents', $allReturnedSlugs);
        $this->assertContains('run_eligibility', $allReturnedSlugs);
        $this->assertContains('generate_reports', $allReturnedSlugs);
    }
}
