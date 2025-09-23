<?php

declare(strict_types=1);

namespace Fintar\RedisRate\Tests\Unit;

use Fintar\RedisRate\Limit;
use Fintar\RedisRate\Result;
use PHPUnit\Framework\TestCase;

class ResultTest extends TestCase
{
    public function test_is_allowed(): void
    {
        $limit = Limit::perSecond(10);
        $result = new Result($limit, 1, 9, -1.0, 0.1);

        $this->assertTrue($result->isAllowed());
        $this->assertFalse($result->isExceeded());
    }

    public function test_is_exceeded(): void
    {
        $limit = Limit::perSecond(10);
        $result = new Result($limit, 0, 0, 0.5, 1.0);

        $this->assertFalse($result->isAllowed());
        $this->assertTrue($result->isExceeded());
    }

    public function test_get_retry_after_seconds(): void
    {
        $limit = Limit::perSecond(10);

        $allowedResult = new Result($limit, 1, 9, -1.0, 0.1);
        $this->assertEquals(0.0, $allowedResult->getRetryAfterSeconds());

        $exceededResult = new Result($limit, 0, 0, 0.5, 1.0);
        $this->assertEquals(0.5, $exceededResult->getRetryAfterSeconds());
    }

    public function test_get_reset_after_seconds(): void
    {
        $limit = Limit::perSecond(10);
        $result = new Result($limit, 1, 9, -1.0, 2.5);

        $this->assertEquals(2.5, $result->getResetAfterSeconds());
    }
}