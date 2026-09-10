<?php

namespace Tests\Feature;

use App\Models\OidcConnection;
use App\Models\RefreshToken;
use App\Models\SsoLoginTransaction;
use App\Models\User;
use App\Services\Sso\OidcDiscoveryService;
use App\Services\Sso\OidcHttpClient;
use App\Services\Sso\SsoPilotService;
use App\Services\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\FakeOidcProvider;
use Tests\TestCase;

class SsoFlowTest extends TestCase
{
    use RefreshDatabase;

    private FakeOidcProvider $provider;

    private OidcConnection $connection;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new FakeOidcProvider;
        $this->app->instance(OidcHttpClient::class, $this->provider);
        $this->connection = OidcConnection::create(['display_name' => 'Fictional SIS', 'issuer' => 'https://sis.example.edu', 'client_id' => 'unifast', 'client_secret' => 'test-secret', 'student_id_claim' => 'student_id']);
        app(OidcDiscoveryService::class)->validate($this->connection);
        $this->connection->update(['state' => 'all_students']);
        $this->student = User::factory()->create(['role' => 'student', 'account_status' => 'active', 'student_id' => 'STU-001', 'name' => 'Fictional Student', 'email' => 'fictional@example.edu']);
    }

    public function test_successful_callback_exchanges_pkce_and_issues_only_local_cookies(): void
    {
        $params = $this->start();
        $context = SsoLoginTransaction::firstOrFail()->context;
        $this->assertSame('S256', $params['code_challenge_method']);
        $this->assertSame(rtrim(strtr(base64_encode(hash('sha256', $context['verifier'], true)), '+/', '-_'), '='), $params['code_challenge']);
        $response = $this->get('/api/auth/sis/callback?'.http_build_query(['state' => $params['state'], 'code' => 'fictional-code']));
        $response->assertRedirectContains('sso_result=signed_in');
        $response->assertCookie('unifast_access')->assertCookie('unifast_refresh');
        $this->assertTrue(RefreshToken::firstOrFail()->remembered);
        $this->assertNull(SsoLoginTransaction::firstOrFail()->context);
        $this->assertStringNotContainsString('fictional-code', $response->headers->get('Location'));
        $exchange = collect($this->provider->requests)->first(fn ($request) => str_ends_with($request[0], '/token'));
        $this->assertSame($context['verifier'], $exchange[1]['code_verifier']);
        $this->get('/api/auth/sis/callback?'.http_build_query(['state' => $params['state'], 'code' => 'replay']))->assertRedirectContains('sso_result=sso_unavailable');
        $this->assertDatabaseCount('refresh_tokens', 1);
    }

    public function test_wrong_browser_consumes_transaction_without_authentication(): void
    {
        $params = $this->start();
        $this->withSession(['sso_browser' => 'another-browser'])->get('/api/auth/sis/callback?'.http_build_query(['state' => $params['state'], 'code' => 'fictional-code']))->assertRedirectContains('sso_result=sso_unavailable');
        $this->assertNotNull(SsoLoginTransaction::firstOrFail()->consumed_at);
        $this->assertDatabaseCount('refresh_tokens', 0);
    }

    public function test_denial_and_expiry_fail_closed(): void
    {
        $params = $this->start();
        $this->get('/api/auth/sis/callback?'.http_build_query(['state' => $params['state'], 'error' => 'access_denied']))->assertRedirectContains('sso_result=sso_not_eligible');
        $this->assertNull(SsoLoginTransaction::firstOrFail()->context);
        $params = $this->start();
        $this->travel(11)->minutes();
        $this->get('/api/auth/sis/callback?'.http_build_query(['state' => $params['state'], 'code' => 'fictional-code']))->assertRedirectContains('sso_result=sso_unavailable');
        $this->assertDatabaseCount('refresh_tokens', 0);
    }

    public function test_disabled_during_login_cannot_complete(): void
    {
        $params = $this->start();
        $this->connection->update(['state' => 'disabled']);
        $this->get('/api/auth/sis/callback?'.http_build_query(['state' => $params['state'], 'code' => 'fictional-code']))->assertRedirectContains('sso_result=sso_unavailable');
        $this->assertDatabaseCount('refresh_tokens', 0);
    }

    public function test_private_pilot_invitation_is_hashed_single_use_and_records_success(): void
    {
        $this->connection->update(['state' => 'pilot']);
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active']);
        $pilots = app(SsoPilotService::class);
        $pilots->select($this->student, $admin);
        $invite = $pilots->invite($this->student, $admin);
        $token = parse_url($invite['url'], PHP_URL_FRAGMENT);
        $this->assertSame(hash('sha256', $token), DB::table('sso_pilot_invitations')->value('token_hash'));
        $this->getJson('/api/auth/capabilities')->assertJsonPath('sis.available', false);
        $this->postJson('/api/auth/sis/redirect')->assertStatus(503);
        $params = $this->start('/api/auth/sis/pilot/'.$token.'/redirect');
        $this->postJson('/api/auth/sis/pilot/'.$token.'/redirect')->assertStatus(503);
        $this->get('/api/auth/sis/callback?'.http_build_query(['state' => $params['state'], 'code' => 'fictional-code']))->assertRedirectContains('sso_result=signed_in');
        $this->assertSame(1, $this->connection->fresh()->pilot_successes);
    }

    public function test_two_factor_issues_no_session_until_verified_and_retains_remember_choice(): void
    {
        $secret = app(TotpService::class)->generateSecret();
        $this->student->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();
        $params = $this->start();
        $this->get('/api/auth/sis/callback?'.http_build_query(['state' => $params['state'], 'code' => 'fictional-code']))->assertRedirectContains('sso_result=two_factor');
        $this->assertDatabaseCount('refresh_tokens', 0);
        $this->postJson('/api/auth/sis/2fa', ['code' => 'invalid'])->assertStatus(422);
        $this->assertDatabaseCount('refresh_tokens', 0);
    }

    private function start(string $path = '/api/auth/sis/redirect'): array
    {
        $response = $this->postJson($path, ['remember_me' => true])->assertOk();
        parse_str(parse_url($response->json('authorization_url'), PHP_URL_QUERY), $params);
        $this->provider->claims = ['iss' => $this->connection->issuer, 'sub' => 'subject-1', 'aud' => $this->connection->client_id,
            'iat' => time(), 'exp' => time() + 300, 'nonce' => $params['nonce'], 'student_id' => 'STU-001', 'email' => $this->student->email,
            'email_verified' => true, 'name' => $this->student->name];

        return $params;
    }
}
