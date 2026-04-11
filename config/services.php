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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'fastapi' => [
        'url' => env('FASTAPI_URL', 'http://localhost:8001'),
        'iam_auth_enabled' => env('FASTAPI_IAM_AUTH_ENABLED', false),
        'iam_audience' => env('FASTAPI_IAM_AUDIENCE'),
        'iam_metadata_url' => env(
            'FASTAPI_IAM_METADATA_URL',
            'http://metadata/computeMetadata/v1/instance/service-accounts/default/identity'
        ),
        'iam_token_cache_seconds' => env('FASTAPI_IAM_TOKEN_CACHE_SECONDS', 3000),
    ],

    // Centralized Supabase configuration
    'supabase' => [
        'url' => env('SUPABASE_URL'),
        'key' => env('SUPABASE_KEY'),
    ],

];
