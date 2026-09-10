<?php

namespace App\Services\Sso;

use App\Models\OidcConnection;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;

class OidcTokenValidator
{
    public function __construct(private OidcDiscoveryService $discovery, private OidcHttpClient $http) {}

    public function exchange(OidcConnection $connection, string $code, array $context): array
    {
        try {
            $metadata = $this->discovery->metadata($connection);
            $form = ['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $context['redirect_uri'], 'code_verifier' => $context['verifier']];
            $basic = [$connection->client_id, $connection->client_secret];
            if (! in_array('client_secret_basic', $metadata['token_endpoint_auth_methods_supported'] ?? ['client_secret_basic'], true)) {
                $form += ['client_id' => $basic[0], 'client_secret' => $basic[1]];
                $basic = null;
            }
            $response = $this->http->json($metadata['token_endpoint'], parse_url($connection->issuer, PHP_URL_HOST), $form, $basic);
            if (! is_string($response['id_token'] ?? null) || strtolower($response['token_type'] ?? '') !== 'bearer') {
                throw new SsoException;
            }
        } catch (\Throwable) {
            $this->discovery->unhealthy($connection);
            throw new SsoException;
        }

        return $this->validate($connection, $response['id_token'], $context['nonce']);
    }

    public function validate(OidcConnection $connection, string $token, string $nonce): array
    {
        try {
            if (strlen($token) > 32768 || ! preg_match('/\A[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\z/D', $token)) {
                throw new SsoException('sso_invalid_token');
            }
            $header = json_decode(JWT::urlsafeB64Decode(explode('.', $token)[0]), true, 8, JSON_THROW_ON_ERROR);
            if (($header['alg'] ?? '') !== 'RS256' || ! is_string($header['kid'] ?? null)
                || isset($header['crit']) || isset($header['jku']) || isset($header['jwk']) || isset($header['x5u'])
                || ! in_array($header['typ'] ?? 'JWT', ['JWT', 'application/jwt'], true)) {
                throw new SsoException('sso_invalid_token');
            }
            $keys = $this->discovery->keys($connection);
            if (! isset($keys[$header['kid']])) {
                $keys = $this->discovery->keys($connection, true);
            }
            if (! isset($keys[$header['kid']])) {
                $this->discovery->unhealthy($connection);
                throw new SsoException;
            }
            $key = JWK::parseKey($keys[$header['kid']], 'RS256');
            $oldLeeway = JWT::$leeway;
            try {
                JWT::$leeway = 30;
                $claims = (array) JWT::decode($token, $key);
            } finally {
                JWT::$leeway = $oldLeeway;
            }
            $aud = $claims['aud'] ?? null;
            $audiences = is_string($aud) ? [$aud] : $aud;
            if (($claims['iss'] ?? null) !== $connection->issuer
                || ! is_array($audiences) || ! in_array($connection->client_id, $audiences, true)
                || ((count($audiences) > 1 || isset($claims['azp'])) && ($claims['azp'] ?? null) !== $connection->client_id)
                || ! is_int($claims['exp'] ?? null) || ! is_int($claims['iat'] ?? null)
                || $claims['exp'] <= time() - 30 || $claims['iat'] > time() + 30
                || $claims['iat'] < time() - 630 || $claims['exp'] <= $claims['iat']
                || ! is_string($claims['nonce'] ?? null) || ! hash_equals($nonce, $claims['nonce'])
                || ! is_string($claims['sub'] ?? null) || $claims['sub'] === '' || strlen($claims['sub']) > 255) {
                throw new SsoException('sso_invalid_token');
            }

            return $claims;
        } catch (SsoException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw new SsoException('sso_invalid_token');
        }
    }
}
