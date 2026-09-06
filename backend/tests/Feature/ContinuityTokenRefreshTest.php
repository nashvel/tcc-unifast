<?php

namespace Tests\Feature;

use App\Models\GoogleWorkspaceConnection;
use App\Services\Continuity\GoogleWorkspaceClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ContinuityTokenRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_transient_failure_preserves_connection_and_next_attempt_can_refresh(): void
    {
        $connection = GoogleWorkspaceConnection::create(['status' => 'connected', 'enabled' => true, 'refresh_token' => 'fixture-refresh']);
        Http::fake(['oauth2.googleapis.com/token' => Http::sequence()
            ->push(['error' => 'temporarily_unavailable'], 503)
            ->push(['access_token' => 'fixture-access', 'expires_in' => 3600, 'refresh_token' => 'fixture-rotated'])]);
        try {
            app(GoogleWorkspaceClient::class)->token();
            $this->fail('An unavailable provider must fail the operation.');
        } catch (HttpException $exception) {
            $this->assertSame(503, $exception->getStatusCode());
        }
        $this->assertSame('connected', $connection->fresh()->status);
        $this->assertTrue($connection->fresh()->enabled);
        $this->assertSame('fixture-access', app(GoogleWorkspaceClient::class)->token());
        $this->assertSame('fixture-rotated', $connection->fresh()->refresh_token);
    }

    public function test_revoked_refresh_token_requires_reconnection_without_exposing_provider_body(): void
    {
        $connection = GoogleWorkspaceConnection::create(['status' => 'connected', 'enabled' => true, 'refresh_token' => 'fixture-refresh']);
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant', 'error_description' => 'private provider details'], 400)]);
        try {
            app(GoogleWorkspaceClient::class)->token();
            $this->fail('A revoked token must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertStringNotContainsString('private provider details', $exception->getMessage());
        }
        $this->assertSame('reconnect_required', $connection->fresh()->status);
        $this->assertFalse($connection->fresh()->enabled);
    }

    public function test_client_configuration_error_does_not_revoke_user_connection(): void
    {
        $connection = GoogleWorkspaceConnection::create(['status' => 'connected', 'enabled' => true, 'refresh_token' => 'fixture-refresh']);
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_client'], 401)]);
        try {
            app(GoogleWorkspaceClient::class)->token();
            $this->fail('An invalid client must be rejected.');
        } catch (ValidationException) {
            $this->assertSame('connected', $connection->fresh()->status);
            $this->assertTrue($connection->fresh()->enabled);
        }
    }
}
