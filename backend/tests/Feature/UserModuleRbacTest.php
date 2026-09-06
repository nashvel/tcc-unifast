<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserModuleRbacTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $staffUser;
    private User $developerUser;
    private User $studentUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $adminRole = Role::where('name', 'admin')->firstOrFail();
        $staffRole = Role::where('name', 'staff')->firstOrFail();
        $developerRole = Role::where('name', 'developer')->firstOrFail();
        $studentRole = Role::where('name', 'student')->firstOrFail();

        $this->adminUser = User::factory()->create([
            'name' => 'Office Head',
            'role' => 'admin',
            'account_status' => 'active',
        ]);
        $this->adminUser->roles()->attach($adminRole);

        $this->staffUser = User::factory()->create([
            'name' => 'Field Reviewer',
            'role' => 'staff',
            'account_status' => 'active',
        ]);
        $this->staffUser->roles()->attach($staffRole);

        $this->developerUser = User::factory()->create([
            'name' => 'System Architect',
            'role' => 'developer',
            'account_status' => 'active',
        ]);
        $this->developerUser->roles()->attach($developerRole);

        $this->studentUser = User::factory()->create([
            'name' => 'Student Applicant',
            'role' => 'student',
            'account_status' => 'active',
        ]);
        $this->studentUser->roles()->attach($studentRole);
    }

    public function test_user_modules_endpoint_returns_operational_modules_and_staff_users(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/rbac/user-modules');

        $response->assertOk();

        $modules = $response->json('data.modules');
        $this->assertCount(12, $modules);

        $moduleKeys = array_column($modules, 'key');
        $this->assertContains('masterlist', $moduleKeys);
        $this->assertContains('batches', $moduleKeys);
        $this->assertContains('documents', $moduleKeys);
        $this->assertContains('academic', $moduleKeys);
        $this->assertContains('eligibility', $moduleKeys);
        $this->assertContains('reports', $moduleKeys);
        $this->assertContains('settings', $moduleKeys);

        // Assert developer-only and grantee-only tools are NOT in the module catalog
        $this->assertNotContains('rbac.manage', $moduleKeys);
        $this->assertNotContains('view_database', $moduleKeys);
        $this->assertNotContains('kyc', $moduleKeys);

        // Assert users list includes ONLY assignable staff/admin, excluding students and developers
        $users = $response->json('data.users');
        $userIds = array_column($users, 'id');

        $this->assertContains($this->adminUser->id, $userIds);
        $this->assertContains($this->staffUser->id, $userIds);
        $this->assertNotContains($this->studentUser->id, $userIds);
        $this->assertNotContains($this->developerUser->id, $userIds); // Developers cannot be assigned!

        // Developer superusers returned separately in developers list
        $developers = $response->json('data.developers');
        $devUser = collect($developers)->firstWhere('id', $this->developerUser->id);
        $this->assertNotNull($devUser);
        $this->assertFalse($devUser['is_assignable']);
        $this->assertTrue($devUser['is_developer']);

        // Non-assignable catalogs are returned
        $nonAssignable = $response->json('data.non_assignable');
        $this->assertArrayHasKey('developer_modules', $nonAssignable);
        $this->assertArrayHasKey('grantee_modules', $nonAssignable);
    }

    public function test_can_update_staff_user_assigned_modules(): void
    {
        // Assign only masterlist and batches to this staff user
        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/rbac/user-modules/{$this->staffUser->id}", [
                'modules' => ['masterlist', 'batches'],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $this->staffUser->id);

        $assigned = $response->json('data.assigned_modules');
        $this->assertContains('masterlist', $assigned);
        $this->assertContains('batches', $assigned);
        $this->assertNotContains('documents', $assigned);

        // Refresh user model and check permissions
        $this->staffUser->refresh();
        $this->assertTrue($this->staffUser->hasPermission('view_masterlist'));
        $this->assertTrue($this->staffUser->hasPermission('masterlist.read'));
        $this->assertTrue($this->staffUser->hasPermission('masterlist.write'));
        $this->assertTrue($this->staffUser->hasPermission('manage_batches'));
        $this->assertTrue($this->staffUser->hasPermission('batches.read'));
        $this->assertTrue($this->staffUser->hasPermission('batches.write'));
        $this->assertFalse($this->staffUser->hasPermission('validate_documents'));
        $this->assertFalse($this->staffUser->hasPermission('documents.read'));
        $this->assertFalse($this->staffUser->hasPermission('documents.write'));
    }

    public function test_can_toggle_single_module_for_user(): void
    {
        // First set to masterlist
        $this->actingAs($this->adminUser)
            ->putJson("/api/rbac/user-modules/{$this->staffUser->id}", [
                'modules' => ['masterlist'],
            ]);

        // Toggle batches to true
        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/rbac/user-modules/{$this->staffUser->id}", [
                'module_key' => 'batches',
                'enabled' => true,
            ]);

        $response->assertOk();
        $assigned = $response->json('data.assigned_modules');
        $this->assertContains('masterlist', $assigned);
        $this->assertContains('batches', $assigned);

        // Toggle masterlist to false
        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/rbac/user-modules/{$this->staffUser->id}", [
                'module_key' => 'masterlist',
                'enabled' => false,
            ]);

        $response->assertOk();
        $assigned = $response->json('data.assigned_modules');
        $this->assertNotContains('masterlist', $assigned);
        $this->assertContains('batches', $assigned);
    }

    public function test_cannot_modify_developer_user_modules(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/rbac/user-modules/{$this->developerUser->id}", [
                'modules' => ['masterlist'],
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Developer accounts have permanent root superuser access and cannot be modified.');
    }

    public function test_cannot_assign_modules_to_student(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/rbac/user-modules/{$this->studentUser->id}", [
                'modules' => ['masterlist'],
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Grantees cannot be assigned operational staff modules.');
    }

    public function test_cannot_assign_invalid_module_key(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/rbac/user-modules/{$this->staffUser->id}", [
                'modules' => ['invalid_module', 'masterlist'],
            ]);

        $response->assertStatus(422);
    }

    public function test_cannot_assign_developer_role_to_staff(): void
    {
        $devRole = Role::where('name', 'developer')->firstOrFail();

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/rbac/users/{$this->staffUser->id}/roles", [
                'role_id' => $devRole->id,
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'The developer role is a permanent root superuser and cannot be assigned.');
    }

    public function test_cannot_assign_student_role_to_staff(): void
    {
        $studentRole = Role::where('name', 'student')->firstOrFail();

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/rbac/users/{$this->staffUser->id}/roles", [
                'role_id' => $studentRole->id,
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'The student/grantee role cannot be assigned to operational staff or admin accounts.');
    }

    public function test_cannot_modify_developer_roles(): void
    {
        $staffRole = Role::where('name', 'staff')->firstOrFail();

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/rbac/users/{$this->developerUser->id}/roles", [
                'role_id' => $staffRole->id,
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Developer accounts have permanent root superuser access and cannot have roles modified.');
    }
}
