<?php

declare(strict_types=1);

namespace Kartubi\RedisRate;

class Result
{
    public $limit;
    public $allowed;
    public $remaining;
    public $retryAfter;
    public $resetAfter;

    public function __construct(Limit $limit, int $allowed, int $remaining, float $retryAfter, float $resetAfter)
    {
        $this->limit = $limit;
        $this->allowed = $allowed;
        $this->remaining = $remaining;
        $this->retryAfter = $retryAfter;
        $this->resetAfter = $resetAfter;
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