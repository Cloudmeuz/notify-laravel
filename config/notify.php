<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    |
    | "production" or "sandbox". This alone decides which base URL below is
    | used - you should not normally need to set NOTIFY_BASE_URL yourself.
    | Notify's server enforces that your client_id/api_key actually belong to
    | this environment; a mismatch is always rejected server-side and is not
    | something this package can (or should) work around.
    |
    */

    'environment' => env('NOTIFY_ENVIRONMENT', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | Base URL override
    |--------------------------------------------------------------------------
    |
    | Leave empty in normal use. Only set NOTIFY_BASE_URL for an advanced or
    | custom deployment (e.g. an on-prem Notify instance) - when set, it takes
    | priority over the environment-based URL below.
    |
    */

    'base_url' => env('NOTIFY_BASE_URL'),

    'urls' => [
        'production' => 'https://api.notify.cloudme.uz/api/v1',
        'sandbox' => 'https://sandbox.notify.cloudme.uz/api/v1',
    ],

    /*
    |--------------------------------------------------------------------------
    | Credentials
    |--------------------------------------------------------------------------
    |
    | client_id and api_key come from a Notify API client (dashboard -> API
    | clients). private_key is that same client's RSA private key, used to
    | sign every /oauth/token request - see cloudme/notify-php's
    | SignatureSigner for the exact algorithm.
    |
    | Supply either NOTIFY_PRIVATE_KEY_PATH (a filesystem path to a PEM file)
    | or NOTIFY_PRIVATE_KEY (the PEM contents inline). If both are set,
    | NOTIFY_PRIVATE_KEY_PATH wins - a file path survives .env escaping
    | (newlines, quoting) far more reliably than an inline multi-line PEM
    | value does.
    |
    */

    'client_id' => env('NOTIFY_CLIENT_ID'),

    'api_key' => env('NOTIFY_API_KEY'),

    'private_key' => env('NOTIFY_PRIVATE_KEY'),

    'private_key_path' => env('NOTIFY_PRIVATE_KEY_PATH'),

    /*
    |--------------------------------------------------------------------------
    | Token storage
    |--------------------------------------------------------------------------
    |
    | "cache"  - persist the access/refresh token pair in a Laravel cache
    |            store (recommended - avoids re-authenticating on every
    |            request/job). See NOTIFY_CACHE_STORE below.
    | "array"  - in-memory only, forgotten as soon as the process ends. Fine
    |            for a short-lived script or a test; anything else will
    |            re-authenticate on every single run.
    |
    */

    'token_store' => env('NOTIFY_TOKEN_STORE', 'cache'),

    'cache' => [
        // Which Laravel cache store to use for the token pair, e.g. "redis",
        // "database", "file". Leave empty to use the app's default store.
        'store' => env('NOTIFY_CACHE_STORE'),

        // Cache keys are prefixed as "{prefix}:{environment}:{client_id}:tokens",
        // so sandbox/production and multiple client_ids never collide.
        'prefix' => env('NOTIFY_CACHE_PREFIX', 'notify'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Incoming webhooks
    |--------------------------------------------------------------------------
    |
    | The signing secret of this API client's webhook (dashboard -> API
    | clients -> Webhook). Used by the "notify.webhook" route middleware to
    | verify every incoming webhook; requests signed more than `tolerance`
    | seconds ago are rejected as replays.
    |
    */

    'webhook' => [
        'secret' => env('NOTIFY_WEBHOOK_SECRET'),
        'tolerance' => (int) env('NOTIFY_WEBHOOK_TOLERANCE', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP behaviour
    |--------------------------------------------------------------------------
    */

    'http' => [
        'timeout' => (float) env('NOTIFY_TIMEOUT', 10),
        'connect_timeout' => (float) env('NOTIFY_CONNECT_TIMEOUT', 5),
        'max_retries' => (int) env('NOTIFY_MAX_RETRIES', 3),
    ],

];
