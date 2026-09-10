<?php

namespace Tests\Unit;

use App\Services\Sso\OidcHttpClient;
use App\Services\Sso\SsoException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OidcSecurityTest extends TestCase
{
    #[DataProvider('unsafeUrls')]
    public function test_rejects_unsafe_provider_urls(string $url): void
    {
        $this->expectException(SsoException::class);
        app(OidcHttpClient::class)->assertUrl($url, 'sis.example.edu');
    }

    public static function unsafeUrls(): array
    {
        return array_map(fn ($url) => [$url], ['http://sis.example.edu', 'https://user:pass@sis.example.edu', 'https://sis.example.edu/#bad', 'https://localhost', 'https://127.0.0.1', 'https://[::1]', 'https://169.254.169.254', 'https://other.example.edu', 'https://sis.example.edu:444']);
    }

    #[DataProvider('unsafeIps')]
    public function test_rejects_private_reserved_and_mapped_addresses(string $ip): void
    {
        $this->assertFalse(OidcHttpClient::publicAddress($ip));
    }

    public static function unsafeIps(): array
    {
        return array_map(fn ($ip) => [$ip], ['127.0.0.1', '10.0.0.1', '172.16.0.1', '192.168.1.1', '169.254.169.254', '100.64.0.1', '224.0.0.1', '0.0.0.0', '::1', '::ffff:127.0.0.1', 'fc00::1', 'fe80::1', '2001:db8::1']);
    }
}
