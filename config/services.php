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
    // ALVY Assistant: the AI help chat on the shop pages (App\Services\Assistant).
    // Runs on Google Gemini by default (GEMINI_API_KEY from https://aistudio.google.com/apikey); set
    // ASSISTANT_PROVIDER=claude to use Anthropic instead. Until the provider's key is set, the chat
    // answers with Quick help only. `php artisan assistant:check` confirms the model exists for the key.
    'assistant' => [
        'enabled'     => env('ASSISTANT_ENABLED', true),
        'provider'    => env('ASSISTANT_PROVIDER', 'gemini'),
        'gemini_key'  => env('GEMINI_API_KEY'),
        'api_key'     => env('ANTHROPIC_API_KEY'),
        'model'       => env('ASSISTANT_MODEL', env('ASSISTANT_PROVIDER', 'gemini') === 'claude' ? 'claude-opus-5-5' : 'gemini-3.8-flash'),
        // When the main Gemini model is overloaded (503/429) after a couple of short retries, these answer instead
        // (comma-separated, tried in order). Empty to only ever use ASSISTANT_MODEL.
        'fallback_models' => env('ASSISTANT_FALLBACK_MODELS', 'gemini-3.6-flash,gemini-3.5-flash'),
        'retry_waits_ms'  => [700, 1500],
        'attempt_timeout' => (int) env('ASSISTANT_ATTEMPT_TIMEOUT', 15),   // seconds one model gets to start answering
        'thinking'    => env('ASSISTANT_THINKING', 'low'),   // Gemini thinking level; empty to leave it to the model
        'timeout'     => (float) env('ASSISTANT_TIMEOUT', 45),
        'daily_limit' => (int) env('ASSISTANT_DAILY_LIMIT', 60),   // questions per visitor per day
    ],

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

    // Text messages (password-change codes by SMS). Without a key the codes go by email only.
    'semaphore' => [
        'key'    => env('SEMAPHORE_API_KEY'),
        'sender' => env('SEMAPHORE_SENDER_NAME'),   // an approved sender name, e.g. ALVY (blank = Semaphore's default)
        'url'    => env('SEMAPHORE_URL', 'https://api.semaphore.co/api/v4/messages'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
