<?php

declare(strict_types=1);

namespace Fintar\RedisRate;

use Illuminate\Support\ServiceProvider;

class RedisRateServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RedisRateLimiter::class, function ($app) {
            return new RedisRateLimiter();
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