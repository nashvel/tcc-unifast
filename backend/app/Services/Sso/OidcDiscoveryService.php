<?php

namespace App\Services\Sso;

use App\Models\OidcConnection;
use Firebase\JWT\JWK;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OidcDiscoveryService
{
    public function __construct(private OidcHttpClient $http) {}

    public function metadata(OidcConnection $connection, bool $refresh = false): array
    {
        $parts = $this->http->assertUrl($connection->issuer);
        if (isset($parts['query'])) {
            throw new SsoException;
        }
        $key = 'sso:discovery:'.$connection->id.':'.$connection->revision;
        if ($refresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, 900, function () use ($connection, $parts) {
            $data = $this->http->json(rtrim($connection->issuer, '/').'/.well-known/openid-configuration', $parts['host']);
            if (($data['issuer'] ?? null) !== $connection->issuer) {
                throw new SsoException;
            }
            foreach (['response_types_supported' => ['code'], 'code_challenge_methods_supported' => ['S256'],
                'id_token_signing_alg_values_supported' => ['RS256'], 'scopes_supported' => ['openid', 'profile', 'email']] as $field => $required) {
                if (! is_array($data[$field] ?? null) || array_diff($required, $data[$field])) {
                    throw new SsoException;
                }
            }
            if (! in_array('authorization_code', $data['grant_types_supported'] ?? ['authorization_code'], true)
                || ! array_intersect(['client_secret_basic', 'client_secret_post'], $data['token_endpoint_auth_methods_supported'] ?? ['client_secret_basic'])) {
                throw new SsoException;
            }
            foreach (['authorization_endpoint', 'token_endpoint', 'jwks_uri'] as $field) {
                if (! is_string($data[$field] ?? null)) {
                    throw new SsoException;
                }
                $this->http->assertUrl($data[$field], $parts['host']);
            }

            return $data;
        });
    }

    public function keys(OidcConnection $connection, bool $refresh = false): array
    {
        $metadata = $this->metadata($connection);
        $key = 'sso:jwks:'.$connection->id.':'.$connection->revision;
        if ($refresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, 900, function () use ($connection, $metadata) {
            $jwks = $this->http->json($metadata['jwks_uri'], parse_url($connection->issuer, PHP_URL_HOST));
            if (! is_array($jwks['keys'] ?? null) || count($jwks['keys']) > 20) {
                throw new SsoException;
            }
            $keys = [];
            foreach ($jwks['keys'] as $jwk) {
                if (! is_array($jwk) || ($jwk['kty'] ?? '') !== 'RSA' || ($jwk['alg'] ?? 'RS256') !== 'RS256'
                    || ($jwk['use'] ?? 'sig') !== 'sig' || (isset($jwk['key_ops']) && $jwk['key_ops'] !== ['verify'])) {
                    continue;
                }
                $kid = $jwk['kid'] ?? null;
                if (! is_string($kid) || $kid === '' || strlen($kid) > 255 || isset($keys[$kid]) || isset($jwk['d'])) {
                    throw new SsoException;
                }
                try {
                    $parsed = JWK::parseKey($jwk, 'RS256');
                    $details = $parsed ? openssl_pkey_get_details(openssl_pkey_get_public($parsed->getKeyMaterial())) : false;
                    if (! $details || $details['bits'] < 2048) {
                        throw new SsoException;
                    }
                } catch (\Throwable) {
                    throw new SsoException;
                }
                $keys[$kid] = $jwk;
            }
            if ($keys === []) {
                throw new SsoException;
            }
            // The network operation happened outside the admin write lock. Only
            // update the exact revision used to obtain these keys.
            OidcConnection::whereKey($connection->id)->where('revision', $connection->revision)
                ->update(['jwks_refreshed_at' => now(), 'updated_at' => now()]);

            return $keys;
        });
    }

    public function validate(OidcConnection $connection): void
    {
        $revision = $connection->revision;
        try {
            $this->metadata($connection, true);
            $this->keys($connection, true);
            DB::transaction(function () use ($connection, $revision) {
                $current = OidcConnection::query()->lockForUpdate()->find($connection->id);
                if (! $current) {
                    throw new SsoException;
                }
                if ((int) $current->revision !== (int) $revision) {
                    throw new SsoException;
                }
                $current->fill(['validated_at' => now(), 'error_code' => null,
                    'state' => in_array($current->state, ['pilot', 'all_students'], true) ? $current->state : 'discovery_verified'])->save();
            });
            SsoAudit::record('discovery_verified');
        } catch (SsoException $exception) {
            // A changed revision must remain draft; do not let an old response
            // alter the replacement connection's health or rollout state.
            $this->unhealthy($connection, $revision);
            throw $exception;
        } catch (\Throwable $error) {
            $this->unhealthy($connection, $revision);
            throw new SsoException;
        }
    }

    public function unhealthy(OidcConnection $connection, ?int $revision = null): void
    {
        $updated = OidcConnection::whereKey($connection->id)
            ->where('revision', $revision ?? $connection->revision)
            ->update(['state' => 'reconnect_required', 'error_code' => 'sso_unavailable', 'updated_at' => now()]);
        if ($updated === 1) {
            SsoAudit::record('connection_unhealthy', ['connection_id' => $connection->id]);
        }
    }
}
