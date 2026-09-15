<?php

return [
    // Activation waits for verified resources, access controls and merge storage.
    'enabled' => env('CONTINUITY_ENABLED', false),
    'sync_secret' => env('CONTINUITY_SYNC_SECRET', ''),
    'signature_max_age_seconds' => 300,
    'google' => [
        'client_id' => env('GOOGLE_WORKSPACE_CLIENT_ID', ''),
        'client_secret' => env('GOOGLE_WORKSPACE_CLIENT_SECRET', ''),
        'redirect_uri' => env('GOOGLE_WORKSPACE_REDIRECT_URI', 'http://127.0.0.1:8000/api/integrations/google-workspace/callback'),
        'http_timeout' => (int) env('GOOGLE_WORKSPACE_HTTP_TIMEOUT', 20),
    ],
];
