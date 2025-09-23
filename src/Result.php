<?php

declare(strict_types=1);

namespace Fintar\RedisRate;

class Result
{
    public function __construct(
        public readonly Limit $limit,
        public readonly int $allowed,
        public readonly int $remaining,
        public readonly float $retryAfter,
        public readonly float $resetAfter
    ) {
    }

    public function isAllowed(): bool
    {
        return $this->allowed > 0;
    }

    public function isExceeded(): bool
    {
        return $this->allowed === 0;
    }

    public function getRetryAfterSeconds(): float
    {
        return $this->retryAfter === -1.0 ? 0.0 : $this->retryAfter;
    }

    public function getResetAfterSeconds(): float
    {
        return $this->resetAfter;
    }
}