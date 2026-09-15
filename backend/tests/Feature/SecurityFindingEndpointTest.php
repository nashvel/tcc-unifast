<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SecurityFinding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityFindingEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_list_and_resolve_security_findings(): void
    {
        $adminRole = Role::create([
            'name' => 'admin',
            'description' => 'Administrator',
            'is_system' => true,
        ]);
        $admin = User::factory()->create([
            'role' => 'student',
            'account_status' => 'active',
        ]);
        $admin->roles()->attach($adminRole);
        $finding = SecurityFinding::create([
            'title' => 'Repeated failed sign-in attempts',
            'category' => 'authentication',
            'severity' => 'high',
            'status' => 'open',
        ]);

        $this->actingAs($admin)
            ->getJson('/api/security/findings')
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $finding->id)
            ->assertJsonPath('data.data.0.status', 'open');

        $this->actingAs($admin)
            ->patchJson("/api/security/findings/{$finding->id}", ['status' => 'resolved'])
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.resolved_by', $admin->id);

        $this->assertDatabaseHas('security_findings', [
            'id' => $finding->id,
            'status' => 'resolved',
            'resolved_by' => $admin->id,
        ]);
    }

    public function test_student_cannot_access_security_findings(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'account_status' => 'active',
        ]);

        $this->actingAs($student)
            ->getJson('/api/security/findings')
            ->assertForbidden();
    }

    public function test_administrator_can_ignore_a_security_finding(): void
    {
        $role = Role::create(['name' => 'admin', 'description' => 'Administrator', 'is_system' => true]);
        $admin = User::factory()->create(['role' => 'student', 'account_status' => 'active']);
        $admin->roles()->attach($role);
        $finding = SecurityFinding::create([
            'title' => 'Known false positive',
            'category' => 'authentication',
            'severity' => 'low',
            'status' => 'open',
        ]);

        $this->actingAs($admin)
            ->patchJson("/api/security/findings/{$finding->id}", ['status' => 'ignored'])
            ->assertOk()
            ->assertJsonPath('data.status', 'ignored');

        $this->assertDatabaseHas('security_findings', ['id' => $finding->id, 'status' => 'ignored']);
    }
}
