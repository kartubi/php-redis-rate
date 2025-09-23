<?php

declare(strict_types=1);

namespace Kartubi\RedisRate\Tests;

use Kartubi\RedisRate\RedisRateServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('config:clear');
    }

    protected function getPackageProviders($app): array
    {
        return [
            RedisRateServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.redis.default', [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ]);
    }
}