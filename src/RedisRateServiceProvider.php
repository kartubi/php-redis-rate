<?php

declare(strict_types=1);

namespace Kartubi\RedisRate;

use Illuminate\Support\ServiceProvider;

class RedisRateServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RedisRateLimiter::class, function ($app) {
            $redisConfig = config('redis-rate.redis');
            $keyPrefix = config('redis-rate.key_prefix', 'rate:');

            // Create dedicated Redis connection for rate limiting
            $redis = $this->createRedisConnection($redisConfig);

            return new RedisRateLimiter($redis, $keyPrefix);
        });

        $this->app->alias(RedisRateLimiter::class, 'redis-rate');
    }

    /**
     * Create a dedicated Redis connection for rate limiting
     */
    private function createRedisConnection(array $config): \Redis
    {
        $redis = new \Redis();

        $redis->connect(
            $config['host'] ?? '127.0.0.1',
            (int) ($config['port'] ?? 6379),
            (float) ($config['timeout'] ?? 5.0)
        );

        if (!empty($config['password'])) {
            $redis->auth($config['password']);
        }

        if (isset($config['database'])) {
            $redis->select((int) $config['database']);
        }

        return $redis;
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