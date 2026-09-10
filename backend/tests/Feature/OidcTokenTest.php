<?php

namespace Tests\Feature;

use App\Models\OidcConnection;
use App\Services\Sso\OidcHttpClient;
use App\Services\Sso\OidcTokenValidator;
use App\Services\Sso\SsoException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FakeOidcProvider;
use Tests\TestCase;

class OidcTokenTest extends TestCase
{
    use RefreshDatabase;

    private FakeOidcProvider $provider;

    private OidcConnection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new FakeOidcProvider;
        $this->app->instance(OidcHttpClient::class, $this->provider);
        $this->connection = OidcConnection::create(['display_name' => 'Test SIS', 'issuer' => 'https://sis.example.edu', 'client_id' => 'unifast', 'client_secret' => 'test-secret']);
    }

    public function test_valid_signed_token_is_accepted(): void
    {
        $claims = app(OidcTokenValidator::class)->validate($this->connection, $this->provider->token($this->claims()), 'test-nonce');
        $this->assertSame('subject-1', $claims['sub']);
    }

    #[DataProvider('invalidClaims')]
    public function test_invalid_claims_fail_closed(string $field, mixed $value): void
    {
        $this->expectException(SsoException::class);
        app(OidcTokenValidator::class)->validate($this->connection, $this->provider->token(array_replace($this->claims(), [$field => $value])), 'test-nonce');
    }

    public static function invalidClaims(): array
    {
        return [['iss', 'https://evil.example.edu'], ['aud', 'other-client'], ['aud', ['unifast', 'other']], ['azp', 'other'], ['exp', 1], ['iat', 9999999999], ['iat', '1'], ['nonce', 'wrong'], ['sub', ''], ['sub', 123]];
    }

    public function test_tampered_signature_is_rejected(): void
    {
        $token = $this->provider->token($this->claims());
        $parts = explode('.', $token);
        $parts[2][0] = $parts[2][0] === 'A' ? 'B' : 'A';
        $this->expectException(SsoException::class);
        app(OidcTokenValidator::class)->validate($this->connection, implode('.', $parts), 'test-nonce');
    }

    private function claims(): array
    {
        return ['iss' => 'https://sis.example.edu', 'sub' => 'subject-1', 'aud' => 'unifast', 'iat' => time(), 'exp' => time() + 300, 'nonce' => 'test-nonce'];
    }
}
