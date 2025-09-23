<?php

declare(strict_types=1);

namespace Kartubi\RedisRate\Tests\Unit;

use Kartubi\RedisRate\Limit;
use PHPUnit\Framework\TestCase;

class LimitTest extends TestCase
{
    public function test_per_second_limit(): void
    {
        $limit = Limit::perSecond(10);

        $this->assertEquals(10, $limit->rate);
        $this->assertEquals(10, $limit->burst);
        $this->assertEquals(1, $limit->period);
    }

    public function test_per_minute_limit(): void
    {
        $limit = Limit::perMinute(60);

        $this->assertEquals(60, $limit->rate);
        $this->assertEquals(60, $limit->burst);
        $this->assertEquals(60, $limit->period);
    }

    public function test_per_hour_limit(): void
    {
        $limit = Limit::perHour(1000);

        $this->assertEquals(1000, $limit->rate);
        $this->assertEquals(1000, $limit->burst);
        $this->assertEquals(3600, $limit->period);
    }

    public function test_custom_limit(): void
    {
        $limit = Limit::custom(50, 100, 300);

        $this->assertEquals(50, $limit->rate);
        $this->assertEquals(100, $limit->burst);
        $this->assertEquals(300, $limit->period);
    }

    public function test_to_string(): void
    {
        $perSecond = Limit::perSecond(10);
        $this->assertEquals('10 req/s (burst 10)', (string) $perSecond);

        $perMinute = Limit::perMinute(60);
        $this->assertEquals('60 req/m (burst 60)', (string) $perMinute);

        $perHour = Limit::perHour(100);
        $this->assertEquals('100 req/h (burst 100)', (string) $perHour);

        $custom = Limit::custom(10, 20, 300);
        $this->assertEquals('10 req/300s (burst 20)', (string) $custom);
    }
}