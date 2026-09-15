<?php

namespace Tests\Support;

use App\Services\Sso\OidcHttpClient;
use App\Services\Sso\SsoException;
use Firebase\JWT\JWT;

class FakeOidcProvider extends OidcHttpClient
{
    public array $claims = [];

    public array $requests = [];

    public string $kid = 'test-key';

    public string $privateKey = '';

    public array $jwk;

    public ?array $metadataOverride = null;

    public function __construct()
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $this->privateKey);
        $rsa = openssl_pkey_get_details($key)['rsa'];
        $this->jwk = ['kid' => $this->kid, 'kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig', 'n' => JWT::urlsafeB64Encode($rsa['n']), 'e' => JWT::urlsafeB64Encode($rsa['e'])];
    }

    public function json(string $url, string $host, ?array $form = null, ?array $basic = null): array
    {
        $this->assertUrl($url, $host);
        $this->requests[] = [$url, $form, $basic];
        if (str_ends_with($url, '/.well-known/openid-configuration')) {
            return $this->metadataOverride ?? ['issuer' => 'https://sis.example.edu', 'authorization_endpoint' => 'https://sis.example.edu/authorize', 'token_endpoint' => 'https://sis.example.edu/token', 'jwks_uri' => 'https://sis.example.edu/jwks', 'response_types_supported' => ['code'], 'code_challenge_methods_supported' => ['S256'], 'id_token_signing_alg_values_supported' => ['RS256'], 'scopes_supported' => ['openid', 'profile', 'email']];
        }
        if (str_ends_with($url, '/jwks')) {
            return ['keys' => [$this->jwk]];
        }
        if (str_ends_with($url, '/token')) {
            return ['token_type' => 'Bearer', 'id_token' => $this->token($this->claims)];
        }
        throw new SsoException;
    }

    public function token(array $claims, string $alg = 'RS256'): string
    {
        return JWT::encode($claims, $this->privateKey, $alg, $this->kid);
    }
}
