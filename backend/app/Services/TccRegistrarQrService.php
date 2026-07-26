<?php

namespace App\Services;

use Illuminate\Support\Str;

class TccRegistrarQrService
{
    public function isValid(?string $payload): bool
    {
        $payload = trim((string) $payload);
        if ($payload === '') {
            return false;
        }

        $domains = collect(config('services.identity.tcc_registrar_domains', []))
            ->filter()
            ->map(fn ($domain) => Str::lower((string) $domain))
            ->values()
            ->all();

        if ($domains === []) {
            return false;
        }

        $normalized = Str::lower($payload);
        $host = null;
        if (filter_var($payload, FILTER_VALIDATE_URL)) {
            $host = Str::lower((string) parse_url($payload, PHP_URL_HOST));
        }

        foreach ($domains as $domain) {
            if ($host && ($host === $domain || Str::endsWith($host, '.'.$domain))) {
                return true;
            }
            if (str_contains($normalized, $domain)) {
                return true;
            }
        }

        return false;
    }
}
