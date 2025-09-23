<?php

declare(strict_types=1);

namespace Kartubi\RedisRate\Facades;

use Kartubi\RedisRate\Limit;
use Kartubi\RedisRate\RedisRateLimiter;
use Kartubi\RedisRate\Result;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Result allow(string $key, Limit $limit)
 * @method static Result allowN(string $key, Limit $limit, int $n)
 * @method static Result allowAtMost(string $key, Limit $limit, int $n)
 * @method static bool reset(string $key)
 */
class RedisRate extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return RedisRateLimiter::class;
    }
}