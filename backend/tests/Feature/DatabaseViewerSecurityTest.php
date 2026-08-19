<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseViewerSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_viewer_requires_the_view_database_permission(): void
    {
        config(['services.database_viewer.enabled' => true]);

        $adminRole = Role::create([
            'name' => 'admin',
            'description' => 'Administrator',
            'is_system' => true,
        ]);
        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);
        $admin->roles()->attach($adminRole);

        $this->actingAs($admin)
            ->getJson('/api/database/tables')
            ->assertForbidden();
    }

    public function test_database_viewer_allows_users_with_the_view_database_permission(): void
    {
        config(['services.database_viewer.enabled' => true]);

        $permission = Permission::create([
            'name' => 'view_database',
            'description' => 'View database viewer',
            'category' => 'Developer Tools',
        ]);
        $role = Role::create([
            'name' => 'developer',
            'description' => 'Developer',
            'is_system' => true,
        ]);
        $role->permissions()->attach($permission);
        $developer = User::factory()->create([
            'role' => 'student',
            'account_status' => 'active',
        ]);
        $developer->roles()->attach($role);

        $this->actingAs($developer)
            ->getJson('/api/database/tables')
            ->assertOk();
    }

    public function test_database_viewer_can_be_disabled_by_configuration(): void
    {
        config(['services.database_viewer.enabled' => false]);

        $admin = $this->databaseViewerUser();

        $this->actingAs($admin)
            ->getJson('/api/database/tables')
            ->assertNotFound();
    }

    public function test_database_viewer_only_lists_allowed_tables(): void
    {
        config([
            'services.database_viewer.enabled' => true,
            'services.database_viewer.allowed_tables' => ['users'],
        ]);

        $admin = $this->databaseViewerUser();

        $response = $this->actingAs($admin)
            ->getJson('/api/database/tables')
            ->assertOk();

        $this->assertContains('users', collect($response->json('data'))->pluck('name'));
        $this->assertNotContains('password_reset_tokens', collect($response->json('data'))->pluck('name'));
    }

    public function test_database_viewer_rejects_tables_outside_the_allowlist(): void
    {
        config([
            'services.database_viewer.enabled' => true,
            'services.database_viewer.allowed_tables' => ['users'],
        ]);

        $admin = $this->databaseViewerUser();

        $this->actingAs($admin)
            ->getJson('/api/database/tables/password_reset_tokens/rows')
            ->assertNotFound();
    }

    public function test_database_viewer_redacts_sensitive_row_fields_and_audits_reads(): void
    {
        config([
            'services.database_viewer.enabled' => true,
            'services.database_viewer.allowed_tables' => ['users'],
        ]);

        $admin = $this->databaseViewerUser();

        $target = User::factory()->create([
            'role' => 'student',
            'account_status' => 'active',
            'remember_token' => 'plain-remember-token',
        ]);

        DB::table('users')
            ->where('id', $target->id)
            ->update(['password' => 'plain-password-hash']);

        $this->actingAs($admin)
            ->getJson('/api/database/tables/users/rows?sort=id&direction=desc')
            ->assertOk()
            ->assertJsonFragment([
                'password' => '[redacted]',
                'remember_token' => '[redacted]',
            ])
            ->assertJsonMissing([
                'password' => 'plain-password-hash',
                'remember_token' => 'plain-remember-token',
            ]);

        $this->assertDatabaseHas(AuditLog::class, [
            'actor' => $admin->email,
            'role' => 'admin',
            'action' => 'database.rows.viewed',
            'module' => 'database_viewer',
            'target' => 'users',
        ]);
    }

    private function databaseViewerUser(): User
    {
        $permission = Permission::create([
            'name' => 'view_database',
            'description' => 'View database viewer',
            'category' => 'Developer Tools',
        ]);
        $role = Role::create([
            'name' => 'admin',
            'description' => 'Administrator',
            'is_system' => true,
        ]);
        $role->permissions()->attach($permission);

        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);
        $admin->roles()->attach($role);

        return $admin;
    }
}
