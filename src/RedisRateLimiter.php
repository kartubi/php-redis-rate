<?php

declare(strict_types=1);

namespace Kartubi\RedisRate;

use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;

class RedisRateLimiter
{
    private const REDIS_PREFIX = 'rate:';
    private const JAN_1_2017 = 1483228800;

    private $redis;

    public function __construct(Connection $redis = null)
    {
        $this->redis = $redis ?: Redis::connection();
    }

    public function allow(string $key, Limit $limit): Result
    {
        return $this->allowN($key, $limit, 1);
    }

    public function allowN(string $key, Limit $limit, int $n): Result
    {
        $script = $this->getAllowNScript();
        $keys = [self::REDIS_PREFIX . $key];
        $args = [$limit->burst, $limit->rate, $limit->period, $n];

        $result = $this->redis->eval($script, 1, ...$keys, ...$args);

        return new Result(
            $limit,
            (int) $result[0],
            (int) $result[1],
            (float) $result[2],
            (float) $result[3]
        );
    }

    public function allowAtMost(string $key, Limit $limit, int $n): Result
    {
        $script = $this->getAllowAtMostScript();
        $keys = [self::REDIS_PREFIX . $key];
        $args = [$limit->burst, $limit->rate, $limit->period, $n];

        $result = $this->redis->eval($script, 1, ...$keys, ...$args);

        return new Result(
            $limit,
            (int) $result[0],
            (int) $result[1],
            (float) $result[2],
            (float) $result[3]
        );
    }

    public function reset(string $key): bool
    {
        return (bool) $this->redis->del(self::REDIS_PREFIX . $key);
    }

    private function getAllowNScript(): string
    {
        return <<<'LUA'
-- this script has side-effects, so it requires replicate commands mode
redis.replicate_commands()

local rate_limit_key = KEYS[1]
local burst = ARGV[1]
local rate = ARGV[2]
local period = ARGV[3]
local cost = tonumber(ARGV[4])

local emission_interval = period / rate
local increment = emission_interval * cost
local burst_offset = emission_interval * burst

-- redis returns time as an array containing two integers: seconds of the epoch
-- time (10 digits) and microseconds (6 digits). for convenience we need to
-- convert them to a floating point number. the resulting number is 16 digits,
-- bordering on the limits of a 64-bit double-precision floating point number.
-- adjust the epoch to be relative to Jan 1, 2017 00:00:00 GMT to avoid floating
-- point problems. this approach is good until "now" is 2,483,228,799 (Wed, 09
-- Sep 2048 01:46:39 GMT), when the adjusted value is 16 digits.
local jan_1_2017 = 1483228800
local now = redis.call("TIME")
now = (now[1] - jan_1_2017) + (now[2] / 1000000)

local tat = redis.call("GET", rate_limit_key)

if not tat then
  tat = now
else
  tat = tonumber(tat)
end

tat = math.max(tat, now)

local new_tat = tat + increment
local allow_at = new_tat - burst_offset

local diff = now - allow_at
local remaining = diff / emission_interval

if remaining < 0 then
  local reset_after = tat - now
  local retry_after = diff * -1
  return {
    0, -- allowed
    0, -- remaining
    tostring(retry_after),
    tostring(reset_after),
  }
end

local reset_after = new_tat - now
if reset_after > 0 then
  redis.call("SET", rate_limit_key, new_tat, "EX", math.ceil(reset_after))
end
local retry_after = -1
return {cost, remaining, tostring(retry_after), tostring(reset_after)}
LUA;
    }

    private function getAllowAtMostScript(): string
    {
        return <<<'LUA'
-- this script has side-effects, so it requires replicate commands mode
redis.replicate_commands()

local rate_limit_key = KEYS[1]
local burst = ARGV[1]
local rate = ARGV[2]
local period = ARGV[3]
local cost = tonumber(ARGV[4])

local emission_interval = period / rate
local burst_offset = emission_interval * burst

-- redis returns time as an array containing two integers: seconds of the epoch
-- time (10 digits) and microseconds (6 digits). for convenience we need to
-- convert them to a floating point number. the resulting number is 16 digits,
-- bordering on the limits of a 64-bit double-precision floating point number.
-- adjust the epoch to be relative to Jan 1, 2017 00:00:00 GMT to avoid floating
-- point problems. this approach is good until "now" is 2,483,228,799 (Wed, 09
-- Sep 2048 01:46:39 GMT), when the adjusted value is 16 digits.
local jan_1_2017 = 1483228800
local now = redis.call("TIME")
now = (now[1] - jan_1_2017) + (now[2] / 1000000)

local tat = redis.call("GET", rate_limit_key)

if not tat then
  tat = now
else
  tat = tonumber(tat)
end

tat = math.max(tat, now)

local diff = now - (tat - burst_offset)
local remaining = diff / emission_interval

if remaining < 1 then
  local reset_after = tat - now
  local retry_after = emission_interval - diff
  return {
    0, -- allowed
    0, -- remaining
    tostring(retry_after),
    tostring(reset_after),
  }
end

if remaining < cost then
  cost = remaining
  remaining = 0
else
  remaining = remaining - cost
end

local increment = emission_interval * cost
local new_tat = tat + increment

local reset_after = new_tat - now
if reset_after > 0 then
  redis.call("SET", rate_limit_key, new_tat, "EX", math.ceil(reset_after))
end

return {
  cost,
  remaining,
  tostring(-1),
  tostring(reset_after),
}
LUA;
    }
}