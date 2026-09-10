<?php

namespace Tests\Feature;

use App\Models\OidcConnection;
use App\Models\User;
use App\Services\Sso\OidcDiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SisSsoConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unconfigured_connection_is_not_publicly_available(): void
    {
        $this->getJson('/api/auth/capabilities')->assertOk()->assertJsonPath('sis.available', false);
        $this->postJson('/api/auth/sis/redirect')->assertStatus(503);
    }

    public function test_only_active_full_session_administrators_can_configure(): void
    {
        $this->getJson('/api/integrations/sis-sso')->assertUnauthorized();
        $this->actingAs(User::factory()->create(['role' => 'student', 'account_status' => 'active']))
            ->putJson('/api/integrations/sis-sso', $this->configuration())->assertForbidden();
    }

    public function test_configuration_is_singleton_draft_with_write_only_encrypted_secret(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'account_status' => 'active']));
        $response = $this->putJson('/api/integrations/sis-sso', $this->configuration());
        $response->assertOk()->assertJsonPath('data.state', 'draft');
        $this->assertStringNotContainsString('fictional-client-secret', $response->getContent());
        $this->assertStringNotContainsString('fictional-client-secret', DB::table('oidc_connections')->value('client_secret'));
        $this->assertSame('fictional-client-secret', OidcConnection::firstOrFail()->client_secret);
        $this->putJson('/api/integrations/sis-sso', $this->configuration())->assertOk();
        $this->assertDatabaseCount('oidc_connections', 1);
        $this->getJson('/api/auth/capabilities')->assertJsonPath('sis.available', false);
        $this->putJson('/api/integrations/sis-sso/rollout', ['mode' => 'all_students', 'confirm' => true])->assertStatus(422);
    }

    public function test_stale_validation_result_cannot_change_a_replacement_revision(): void
    {
        $connection = OidcConnection::create(['display_name' => 'Old SIS', 'issuer' => 'https://sis.example.edu', 'client_id' => 'old', 'client_secret' => 'old-secret']);
        $stale = $connection->replicate();
        $stale->id = $connection->id;
        $stale->exists = true;
        $connection->update(['revision' => 2, 'state' => 'draft', 'validated_at' => null, 'error_code' => null]);
        app(OidcDiscoveryService::class)->unhealthy($stale);
        $this->assertSame('draft', $connection->fresh()->state);
        $this->assertNull($connection->fresh()->error_code);
    }

    private function configuration(): array
    {
        return ['display_name' => 'Fictional SIS', 'issuer' => 'https://sis.example.edu', 'client_id' => 'unifast', 'client_secret' => 'fictional-client-secret', 'student_id_claim' => 'student_id'];
    }
}
