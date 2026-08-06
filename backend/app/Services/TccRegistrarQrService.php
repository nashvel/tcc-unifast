<?php

namespace App\Services;

use Illuminate\Support\Str;

class TccRegistrarQrService
{
    /**
     * @var list<string>
     */
    private const STUDENT_ID_QUERY_KEYS = [
        'sid',
        'student_id',
        'studentid',
        'student-id',
        'student_number',
        'studentnumber',
    ];

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

    /**
     * Best-effort parse of a QR payload for staff review. Never throws.
     *
     * @return array{
     *     parseable: bool,
     *     kind: 'url'|'text'|null,
     *     raw: ?string,
     *     scheme: ?string,
     *     host: ?string,
     *     path: ?string,
     *     query: array<string, string>,
     *     student_id: ?string
     * }
     */
    public function extract(?string $payload): array
    {
        $raw = trim((string) $payload);
        if ($raw === '') {
            return $this->emptyExtraction();
        }

        if (! filter_var($raw, FILTER_VALIDATE_URL)) {
            return [
                'parseable' => false,
                'kind' => 'text',
                'raw' => $raw,
                'scheme' => null,
                'host' => null,
                'path' => null,
                'query' => [],
                'student_id' => $this->guessStudentIdFromText($raw),
            ];
        }

        $parts = parse_url($raw);
        $scheme = isset($parts['scheme']) ? Str::lower((string) $parts['scheme']) : null;
        $host = isset($parts['host']) ? Str::lower((string) $parts['host']) : null;
        $path = isset($parts['path']) ? (string) $parts['path'] : null;
        $query = $this->parseQuery(isset($parts['query']) ? (string) $parts['query'] : '');
        $studentId = $this->studentIdFromUrl($path, $query);

        return [
            'parseable' => true,
            'kind' => 'url',
            'raw' => $raw,
            'scheme' => $scheme,
            'host' => $host !== '' ? $host : null,
            'path' => $path !== null && $path !== '' ? $path : null,
            'query' => $query,
            'student_id' => $studentId,
        ];
    }

    /**
     * @return array{
     *     parseable: bool,
     *     kind: null,
     *     raw: null,
     *     scheme: null,
     *     host: null,
     *     path: null,
     *     query: array<string, string>,
     *     student_id: null
     * }
     */
    public function emptyExtraction(): array
    {
        return [
            'parseable' => false,
            'kind' => null,
            'raw' => null,
            'scheme' => null,
            'host' => null,
            'path' => null,
            'query' => [],
            'student_id' => null,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function parseQuery(string $query): array
    {
        if ($query === '') {
            return [];
        }

        $parsed = [];
        parse_str($query, $parsed);

        $out = [];
        foreach ($parsed as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }
            if (is_array($value)) {
                $value = implode(',', array_map(static fn ($item) => (string) $item, $value));
            }
            $out[$key] = trim((string) $value);
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $query
     */
    private function studentIdFromUrl(?string $path, array $query): ?string
    {
        foreach (self::STUDENT_ID_QUERY_KEYS as $key) {
            foreach ($query as $queryKey => $value) {
                if (Str::lower($queryKey) === $key && $this->looksLikeStudentId($value)) {
                    return $value;
                }
            }
        }

        if ($path === null || $path === '' || $path === '/') {
            return null;
        }

        $segments = array_values(array_filter(
            explode('/', trim($path, '/')),
            static fn ($segment) => $segment !== '',
        ));
        if ($segments === []) {
            return null;
        }

        $last = (string) $segments[array_key_last($segments)];
        $verifyIndex = null;
        foreach ($segments as $index => $segment) {
            if (Str::lower((string) $segment) === 'verify') {
                $verifyIndex = $index;
            }
        }

        if ($verifyIndex !== null && isset($segments[$verifyIndex + 1])) {
            $afterVerify = (string) $segments[$verifyIndex + 1];
            if ($this->looksLikeStudentId($afterVerify)) {
                return $afterVerify;
            }
        }

        if ($this->looksLikeStudentId($last) && Str::lower($last) !== 'verify') {
            return $last;
        }

        return null;
    }

    private function guessStudentIdFromText(string $raw): ?string
    {
        if (preg_match_all('/\b([A-Za-z]{2,8}-[A-Za-z0-9]{1,24}(?:-[A-Za-z0-9]{1,20})?)\b/', $raw, $matches) >= 1) {
            foreach ($matches[1] as $candidate) {
                $candidate = (string) $candidate;
                if ($this->looksLikeStudentId($candidate) && preg_match('/\d/', $candidate) === 1) {
                    return $candidate;
                }
            }
        }

        return $this->looksLikeStudentId($raw) ? $raw : null;
    }

    private function looksLikeStudentId(string $value): bool
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 40) {
            return false;
        }

        // Avoid treating generic path words as IDs.
        if (in_array(Str::lower($value), ['verify', 'student', 'id', 'login', 'home', 'fake'], true)) {
            return false;
        }

        // Compact IDs only (e.g. STU-42, STU-VAULT-3) — reject sentence-like strings.
        if (substr_count($value, '-') > 2) {
            return false;
        }

        return (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{1,38}$/', $value);
    }
}
