<?php

namespace Tests\Feature;

use App\Models\GoogleWorkspaceConnection;
use App\Models\User;
use App\Services\Continuity\GoogleWorkspaceClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ContinuityConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_tokens_are_encrypted_and_excluded_from_serialization(): void
    {
        $connection = GoogleWorkspaceConnection::create(['access_token' => 'private-access', 'refresh_token' => 'private-refresh']);
        $this->assertSame('private-access', $connection->fresh()->access_token);
        $this->assertNotSame('private-access', DB::table('google_workspace_connections')->value('access_token'));
        $this->assertArrayNotHasKey('access_token', $connection->toArray());
        $this->assertArrayNotHasKey('refresh_token', $connection->toArray());
    }

    public function test_staff_and_students_cannot_read_integration_settings(): void
    {
        foreach (['staff', 'student'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'account_status' => 'active']))
                ->getJson('/api/integrations/google-workspace/status')->assertForbidden();
        }
    }

    public function test_admin_can_read_safe_status_without_google_credentials(): void
    {
        config(['continuity.google.client_id' => '', 'continuity.google.client_secret' => '']);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'account_status' => 'active']))
            ->getJson('/api/integrations/google-workspace/status')->assertOk()->assertJsonPath('data.configured', false);
        Http::assertNothingSent();
    }

    public function test_inactive_administrator_cannot_access_continuity_routes(): void
    {
        Http::fake();
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'suspended']);
        $this->actingAs($admin)->getJson('/api/integrations/google-workspace/status')->assertForbidden();
        $this->actingAs($admin)->getJson('/api/continuity/reviews')->assertForbidden();
        $this->actingAs($admin)->postJson('/api/integrations/google-workspace/sync')->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_callback_consumes_state_and_rejects_replay(): void
    {
        Http::fake();
        $this->withSession(['continuity_oauth' => ['state' => 'expected', 'user_id' => 1, 'expires_at' => now()->addMinute()->timestamp]])
            ->getJson('/api/integrations/google-workspace/callback?state=wrong&code=code')->assertUnprocessable();
        $this->getJson('/api/integrations/google-workspace/callback?state=expected&code=code')->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_callback_saves_verified_connection_without_leaking_tokens(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active']);
        config(['continuity.google.client_id' => 'client', 'continuity.google.client_secret' => 'secret']);
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'access', 'refresh_token' => 'refresh', 'expires_in' => 3600, 'scope' => implode(' ', GoogleWorkspaceClient::SCOPES)]),
            'www.googleapis.com/oauth2/v2/userinfo' => Http::response(['id' => 'google-id', 'email' => 'continuity@example.edu', 'verified_email' => true]),
        ]);
        $this->withSession(['continuity_oauth' => ['state' => 'expected', 'user_id' => $admin->id, 'expires_at' => now()->addMinute()->timestamp]])
            ->get('/api/integrations/google-workspace/callback?state=expected&code=code')->assertRedirect();
        $this->assertSame('refresh', GoogleWorkspaceConnection::first()->refresh_token);
        $this->actingAs($admin)->getJson('/api/integrations/google-workspace/status')->assertOk()->assertJsonMissing(['access_token' => 'access', 'refresh_token' => 'refresh']);
    }
}
