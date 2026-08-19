<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_relationship_roles_grant_route_access_even_when_legacy_role_column_differs(): void
    {
        $adminRole = Role::create([
            'name' => 'admin',
            'description' => 'Administrator',
            'is_system' => true,
        ]);
        $user = User::factory()->create([
            'role' => 'student',
            'account_status' => 'active',
        ]);
        $user->roles()->attach($adminRole);

        $this->actingAs($user)
            ->getJson('/api/batches')
            ->assertOk();
    }

    public function test_relationship_roles_override_legacy_role_column_when_assigned(): void
    {
        $studentRole = Role::create([
            'name' => 'student',
            'description' => 'Student',
            'is_system' => true,
        ]);
        $user = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);
        $user->roles()->attach($studentRole);

        $this->actingAs($user)
            ->getJson('/api/batches')
            ->assertForbidden();
    }

    public function test_legacy_role_column_still_authorizes_users_without_rbac_assignments(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);

        $this->actingAs($user)
            ->getJson('/api/batches')
            ->assertOk();
    }
}
