<?php

declare(strict_types=1);

namespace Fintar\RedisRate\Tests\Feature;

use Fintar\RedisRate\Facades\RedisRate;
use Fintar\RedisRate\Limit;
use Fintar\RedisRate\Tests\TestCase;
use Illuminate\Support\Facades\Redis;

class RedisRateLimiterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('redis') && !class_exists('Predis\Client')) {
            $this->markTestSkipped('Redis extension or Predis is required for this test.');
        }

        try {
            Redis::connection()->ping();
        } catch (\Exception $e) {
            $this->markTestSkipped('Redis connection is not available: ' . $e->getMessage());
        }

        Redis::connection()->flushdb();
    }

    public function test_allows_requests_within_limit(): void
    {
        $limit = Limit::perSecond(10);
        $key = 'test:allow';

        $result = RedisRate::allow($key, $limit);

        $this->assertTrue($result->isAllowed());
        $this->assertEquals(1, $result->allowed);
        $this->assertEquals(9, $result->remaining);
        $this->assertEquals(-1.0, $result->retryAfter);
    }

    public function test_denies_requests_exceeding_limit(): void
    {
        $limit = Limit::perSecond(2);
        $key = 'test:deny';

        RedisRate::allow($key, $limit);
        RedisRate::allow($key, $limit);

        $result = RedisRate::allow($key, $limit);

        $this->assertTrue($result->isExceeded());
        $this->assertEquals(0, $result->allowed);
        $this->assertEquals(0, $result->remaining);
        $this->assertGreaterThan(0, $result->retryAfter);
    }

    public function test_allow_n_requests(): void
    {
        $limit = Limit::perSecond(10);
        $key = 'test:allowN';

        $result = RedisRate::allowN($key, $limit, 5);

        $this->assertTrue($result->isAllowed());
        $this->assertEquals(5, $result->allowed);
        $this->assertEquals(5, $result->remaining);
    }

    public function test_allow_at_most_requests(): void
    {
        $limit = Limit::perSecond(10);
        $key = 'test:allowAtMost';

        RedisRate::allowN($key, $limit, 8);

        $result = RedisRate::allowAtMost($key, $limit, 5);

        $this->assertTrue($result->isAllowed());
        $this->assertEquals(2, $result->allowed);
        $this->assertEquals(0, $result->remaining);
    }

    public function test_reset_removes_rate_limit(): void
    {
        $limit = Limit::perSecond(1);
        $key = 'test:reset';

        RedisRate::allow($key, $limit);
        $result = RedisRate::allow($key, $limit);

        $this->assertTrue($result->isExceeded());

        RedisRate::reset($key);

        $result = RedisRate::allow($key, $limit);
        $this->assertTrue($result->isAllowed());
    }

    public function test_per_minute_limit(): void
    {
        $limit = Limit::perMinute(60);
        $key = 'test:perMinute';

        $result = RedisRate::allow($key, $limit);

        $this->assertTrue($result->isAllowed());
        $this->assertEquals($limit, $result->limit);
        $this->assertEquals(60, $result->limit->rate);
        $this->assertEquals(60, $result->limit->period);
    }

    public function test_per_hour_limit(): void
    {
        $limit = Limit::perHour(100);
        $key = 'test:perHour';

        $result = RedisRate::allow($key, $limit);

        $this->assertTrue($result->isAllowed());
        $this->assertEquals($limit, $result->limit);
        $this->assertEquals(100, $result->limit->rate);
        $this->assertEquals(3600, $result->limit->period);
    }

    public function test_custom_limit_with_burst(): void
    {
        $limit = Limit::custom(10, 20, 60);
        $key = 'test:custom';

        $result = RedisRate::allowN($key, $limit, 15);

        $this->assertTrue($result->isAllowed());
        $this->assertEquals(15, $result->allowed);
        $this->assertEquals(5, $result->remaining);
    }
}