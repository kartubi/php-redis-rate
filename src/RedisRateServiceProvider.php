<?php

declare(strict_types=1);

namespace Kartubi\RedisRate;

use Illuminate\Support\ServiceProvider;

class RedisRateServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RedisRateLimiter::class, function ($app) {
            // Try to use dedicated rate_limiter connection first
            $connectionName = config('redis-rate.connection', 'rate_limiter');

            try {
                // Check if rate_limiter connection exists
                $redis = \Illuminate\Support\Facades\Redis::connection($connectionName);
                $keyPrefix = config('redis-rate.key_prefix', 'rate:');

                return new RedisRateLimiter($redis, $keyPrefix);
            } catch (\Exception $e) {
                // Fallback to default connection if rate_limiter doesn't exist
                $redis = \Illuminate\Support\Facades\Redis::connection();
                $keyPrefix = config('redis-rate.key_prefix', 'rate:');

                return new RedisRateLimiter($redis, $keyPrefix);
            }
        });

        $this->app->alias(RedisRateLimiter::class, 'redis-rate');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/redis-rate.php' => config_path('redis-rate.php'),
            ], 'redis-rate-config');

            $this->commands([
                Commands\RedisRateCommand::class,
                Commands\SetupRedisCommand::class,
            ]);
        }

        $this->mergeConfigFrom(
            __DIR__ . '/../config/redis-rate.php',
            'redis-rate'
        );
    }

    public function provides(): array
    {
        return [RedisRateLimiter::class, 'redis-rate'];
    }
}