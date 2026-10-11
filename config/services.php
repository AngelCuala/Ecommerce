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

    // Checks that uploaded ID photos really are government-issued IDs.
    // Without an API key the check is skipped and uploads are flagged for admin review.
    'id_check' => [
        'enabled' => env('ID_CHECK_ENABLED', true),
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model'   => env('ID_CHECK_MODEL', 'claude-opus-5-5'),
        'timeout' => (float) env('ID_CHECK_TIMEOUT', 30),
    ],

    // Sign in with Google (Laravel Socialite). Create OAuth credentials at
    // https://console.cloud.google.com/apis/credentials and add the callback URL below.
    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
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

];
