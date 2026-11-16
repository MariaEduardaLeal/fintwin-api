<?php

return [
    /*
    |--------------------------------------------------------------------------
    | External Financial & AI Integrations
    |--------------------------------------------------------------------------
    |
    | All credentials are loaded strictly from environment variables (.env)
    | and must never be hardcoded in source control.
    |
    */

    'pluggy' => [
        'base_url' => env('PLUGGY_BASE_URL', 'https://api.pluggy.ai'),
        'client_id' => env('PLUGGY_CLIENT_ID'),
        'client_secret' => env('PLUGGY_CLIENT_SECRET'),
        'timeout' => (int) env('PLUGGY_TIMEOUT', 15),
        'connect_timeout' => (int) env('PLUGGY_CONNECT_TIMEOUT', 5),
        'api_key_ttl' => (int) env('PLUGGY_API_KEY_TTL', 6000),
    ],

    'deepseek' => [
        'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),
        'api_key' => env('DEEPSEEK_API_KEY'),
        'model' => env('DEEPSEEK_MODEL', 'deepseek-chat'),
        'timeout' => (int) env('DEEPSEEK_TIMEOUT', 30),
        'connect_timeout' => (int) env('DEEPSEEK_CONNECT_TIMEOUT', 5),
    ],

    'brapi' => [
        'base_url' => env('BRAPI_BASE_URL', 'https://brapi.dev/api'),
        'api_token' => env('BRAPI_API_TOKEN'),
        'timeout' => (int) env('BRAPI_TIMEOUT', 15),
        'connect_timeout' => (int) env('BRAPI_CONNECT_TIMEOUT', 5),
    ],
];
