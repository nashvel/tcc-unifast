<?php

namespace App\Services\Sso;

/** HTTPS-only transport with DNS pinning, no redirects/proxies, and bounded bodies. */
class OidcHttpClient
{
    public function assertUrl(string $url, ?string $allowedHost = null): array
    {
        $parts = parse_url($url);
        if (! $parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || isset($parts['user'], $parts['pass']) || isset($parts['user']) || isset($parts['fragment'])
            || (isset($parts['port']) && $parts['port'] !== 443)
            || ! preg_match('/\A[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?\z/D', $parts['host'])
            || ($allowedHost !== null && $parts['host'] !== $allowedHost)
            || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) {
            throw new SsoException;
        }
        if (filter_var($parts['host'], FILTER_VALIDATE_IP) || ! str_contains($parts['host'], '.')) {
            throw new SsoException;
        }

        return $parts;
    }

    public static function publicAddress(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_GLOBAL_RANGE)) {
            return false;
        }
        // Only native global IPv6 unicast; exclude translation and transition ranges.
        if (str_contains($ip, ':')) {
            return preg_match('/^[23][0-9a-f]{3}:/i', $ip) === 1
                && ! preg_match('/^(2001:|2002:)/i', $ip);
        }

        return (int) explode('.', $ip)[0] < 224;
    }

    protected function resolve(string $host): array
    {
        $records = dns_get_record($host, DNS_A | DNS_AAAA);

        return array_values(array_filter(array_map(fn ($row) => $row['ip'] ?? $row['ipv6'] ?? null, $records ?: [])));
    }

    public function json(string $url, string $host, ?array $form = null, ?array $basic = null): array
    {
        $parts = $this->assertUrl($url, $host);
        $addresses = $this->resolve($parts['host']);
        if ($addresses === [] || count(array_filter($addresses, self::publicAddress(...))) !== count($addresses)) {
            throw new SsoException;
        }
        $ip = $addresses[0];
        $body = '';
        $curl = curl_init($url);
        if ($curl === false) {
            throw new SsoException;
        }
        try {
            curl_setopt_array($curl, [
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROXY => '', CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 8,
                CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_RESOLVE => [$parts['host'].':443:'.(str_contains($ip, ':') ? '['.$ip.']' : $ip)],
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
                CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
                    if (strlen($body) + strlen($chunk) > 262144) {
                        return 0;
                    }
                    $body .= $chunk;

                    return strlen($chunk);
                },
            ]);
            if ($form !== null) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($form, '', '&', PHP_QUERY_RFC3986));
            }
            if ($basic !== null) {
                curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                curl_setopt($curl, CURLOPT_USERPWD, urlencode($basic[0]).':'.urlencode($basic[1]));
            }
            $ok = curl_exec($curl);
            $type = strtolower((string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE));
            if ($ok === false || curl_getinfo($curl, CURLINFO_RESPONSE_CODE) !== 200
                || ! preg_match('~^application/(?:json|jwk-set\+json)(?:;|$)~', $type)) {
                throw new SsoException;
            }
            $json = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($json) || array_is_list($json)) {
                throw new SsoException;
            }

            return $json;
        } catch (\Throwable) {
            // Never retain a transport exception, which may include a code/secret.
            throw new SsoException;
        } finally {
            curl_close($curl);
        }
    }
}
