<?php

// Comma-separated list so the deployed SPA and local dev servers can both
// reach the same API. Values must be scheme + host (+ port), no trailing slash.
// A blank FRONTEND_URL is treated as unset rather than "allow nothing", since
// env() returns '' (not the default) for a key that exists with no value.
$allowedOrigins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('FRONTEND_URL', '')),
), static fn (string $origin): bool => $origin !== ''));

if ($allowedOrigins === []) {
    $allowedOrigins = ['http://localhost:5173'];
}

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
