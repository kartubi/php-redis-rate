<?php

declare(strict_types=1);

namespace Kartubi\RedisRate;

class Limit
{
    public function __construct(
        public readonly int $rate,
        public readonly int $burst,
        public readonly int $period
    ) {
    }

    public static function perSecond(int $rate): self
    {
        return new self($rate, $rate, 1);
    }

    public static function perMinute(int $rate): self
    {
        return new self($rate, $rate, 60);
    }

    public static function perHour(int $rate): self
    {
        return new self($rate, $rate, 3600);
    }

    public static function custom(int $rate, int $burst, int $periodInSeconds): self
    {
        return new self($rate, $burst, $periodInSeconds);
    }

    public function __toString(): string
    {
        $periodStr = match ($this->period) {
            1 => 's',
            60 => 'm',
            3600 => 'h',
            default => $this->period . 's'
        };

        return "{$this->rate} req/{$periodStr} (burst {$this->burst})";
    }
}