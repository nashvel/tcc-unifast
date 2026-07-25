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

];
