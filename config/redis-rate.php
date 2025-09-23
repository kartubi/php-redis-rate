<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Redis Connection
    |--------------------------------------------------------------------------
    |
    | This option controls the default Redis connection that will be used
    | for rate limiting. If null, the default Redis connection will be used.
    |
    */

    'connection' => env('REDIS_RATE_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Key Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix will be added to all rate limiting keys stored in Redis.
    | Useful for avoiding conflicts with other applications using the same
    | Redis instance.
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