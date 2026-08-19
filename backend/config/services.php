<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'tcc_unifast_n8n' => [
        'webhook_url' => env('TCC_UNIFAST_N8N_WEBHOOK_URL'),
        'webhook_header' => env('TCC_UNIFAST_N8N_WEBHOOK_HEADER', 'X-TCC-UniFAST-Key'),
        'webhook_secret' => env('TCC_UNIFAST_N8N_WEBHOOK_SECRET'),
        'endpoint_secret' => env('TCC_UNIFAST_SYNC_ENDPOINT_SECRET'),
        'timeout' => (int) env('TCC_UNIFAST_N8N_TIMEOUT', 15),
        'student_table' => env('TCC_UNIFAST_STUDENT_TABLE', 'students'),
    ],

    'tcc_public' => [
        'home_url' => env('TCC_PUBLIC_HOME_URL', 'https://api.tcc.edu.ph/api/v1/home'),
        'site_url' => env('TCC_PUBLIC_SITE_URL', 'https://tcc.edu.ph'),
        'api_url' => env('TCC_PUBLIC_API_URL', 'https://api.tcc.edu.ph'),
        'timeout' => (int) env('TCC_PUBLIC_TIMEOUT', 10),
        'cache_seconds' => (int) env('TCC_PUBLIC_CACHE_SECONDS', 300),
    ],

    'ocr' => [
        'url' => env('OCR_SERVICE_URL', 'http://127.0.0.1:8001'),
        'timeout' => (int) env('OCR_SERVICE_TIMEOUT', 120),
    ],

    'face_api' => [
        'provider' => env('FACE_API_PROVIDER', 'mock'),
        'url' => env('FACE_API_URL'),
        'key' => env('FACE_API_KEY'),
        'timeout' => (int) env('FACE_API_TIMEOUT', 30),
        'threshold' => (float) env('FACE_API_MATCH_THRESHOLD', 85),
    ],

    'identity' => [
        'tcc_registrar_domains' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('TCC_REGISTRAR_DOMAINS', 'registrar.tcc.edu.ph,sis.tcc.edu.ph,tcc.edu.ph'))
        ))),
        'ocr_space_api_key' => env('OCR_SPACE_API_KEY'),
        'ocr_space_timeout' => (int) env('OCR_SPACE_TIMEOUT', 60),
        'authenticity_service_url' => env('AUTHENTICITY_SERVICE_URL'),
        // Vault / legacy single-threshold face match (pass if distance < threshold).
        'face_match_threshold' => (float) env('IDENTITY_FACE_MATCH_THRESHOLD', 0.5),
        // Onboarding three-tier zones (Policy Settings may override when set).
        // distance < pass_max → activate; pass_max <= distance < review_max → staff review; else retry (no block).
        'face_pass_max' => (float) env('IDENTITY_FACE_PASS_MAX', 0.45),
        'face_review_max' => (float) env('IDENTITY_FACE_REVIEW_MAX', 0.60),
    ],

    'gradeslip_qr' => [
        // Optional override; default resolves python/.venv then system `python`.
        'python' => env('GRADESLIP_QR_PYTHON'),
        'timeout' => (int) env('GRADESLIP_QR_TIMEOUT', 60),
    ],

    'auth' => [
        // Local/dev only: skip captcha verification when true.
        'dev_bypass_captcha' => filter_var(env('DEV_BYPASS_CAPTCHA', false), FILTER_VALIDATE_BOOLEAN),
        // Login attempts per minute per IP (use a higher value locally).
        'login_throttle_per_minute' => max(1, (int) env(
            'LOGIN_THROTTLE_PER_MINUTE',
            env('APP_ENV') === 'local' ? 60 : 5
        )),
        'access_cookie' => env('AUTH_ACCESS_COOKIE', 'unifast_access'),
        'refresh_cookie' => env('AUTH_REFRESH_COOKIE', 'unifast_refresh'),
        'access_token_ttl_minutes' => max(1, (int) env('AUTH_ACCESS_TTL_MINUTES', 20)),
        'refresh_token_ttl_days' => max(1, (int) env('AUTH_REFRESH_TTL_DAYS', 7)),
        // lax for same-origin (Vite proxy); none + Secure for cross-origin SPA.
        'cookie_same_site' => env('AUTH_COOKIE_SAMESITE', 'lax'),
        'cookie_secure' => filter_var(
            env('AUTH_COOKIE_SECURE', env('APP_ENV') === 'production'),
            FILTER_VALIDATE_BOOLEAN
        ),
        'cookie_domain' => env('AUTH_COOKIE_DOMAIN'),
        'refresh_throttle_per_minute' => max(1, (int) env('AUTH_REFRESH_THROTTLE_PER_MINUTE', 30)),
    ],

    'requirement_vault' => [
        // Confirm is a rare action, but failed validation still counts toward the
        // limiter — keep prod protective while leaving local/dev room for QA retries.
        'confirm_throttle_per_minute' => max(1, (int) env(
            'VAULT_CONFIRM_THROTTLE_PER_MINUTE',
            env('APP_ENV') === 'local' ? 60 : 20
        )),
    ],

    'database_viewer' => [
        // Developer convenience surface. Keep disabled unless explicitly enabled
        // outside local/testing because it can expose raw table data.
        'enabled' => filter_var(
            env('FEATURE_DATABASE_VIEWER', in_array(env('APP_ENV'), ['local', 'testing'], true)),
            FILTER_VALIDATE_BOOLEAN
        ),
        'allowed_tables' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env(
                'DATABASE_VIEWER_ALLOWED_TABLES',
                'users,roles,permissions,role_user,permission_role,batches,masterlist_imports,masterlist_rows,grantees,kyc_profiles,academic_records,document_submissions,requirement_identity_checks,submission_pipeline_results,billing_reports,billing_report_items,audit_logs'
            ))
        ))),
    ],

];
