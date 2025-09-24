<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Redis Connection Configuration
    |--------------------------------------------------------------------------
    |
    | Configure Redis connection specifically for rate limiting.
    | This creates a dedicated connection separate from Laravel's main Redis.
    |
    */

    'redis' => [
        'host' => env('REDIS_RATE_HOST', env('REDIS_HOST', '127.0.0.1')),
        'port' => env('REDIS_RATE_PORT', env('REDIS_PORT', 6379)),
        'password' => env('REDIS_RATE_PASSWORD', env('REDIS_PASSWORD', null)),
        'database' => env('REDIS_RATE_DB', 0),
        'timeout' => env('REDIS_RATE_TIMEOUT', 5.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Key Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix will be added to all rate limiting keys stored in Redis.
    | No Laravel app-name prefix will be added.
    |
    */

    'key_prefix' => env('REDIS_RATE_PREFIX', 'rate:'),

    /*
    |--------------------------------------------------------------------------
    | Default Limits
    |--------------------------------------------------------------------------
    |
    | You can define default rate limits that can be referenced by name
    | throughout your application.
    |
    */

    'limits' => [
        'api' => [
            'rate' => 60,
            'period' => 60, // seconds
            'burst' => 10,
        ],
        'login' => [
            'rate' => 5,
            'period' => 300, // 5 minutes
            'burst' => 5,
        ],
        'upload' => [
            'rate' => 10,
            'period' => 3600, // 1 hour
            'burst' => 20,
        ],
    ],
];