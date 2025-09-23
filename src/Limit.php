<?php

declare(strict_types=1);

namespace Kartubi\RedisRate;

class Limit
{
    public $rate;
    public $burst;
    public $period;

    public function __construct(int $rate, int $burst, int $period)
    {
        $this->rate = $rate;
        $this->burst = $burst;
        $this->period = $period;
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
        switch ($this->period) {
            case 1:
                $periodStr = 's';
                break;
            case 60:
                $periodStr = 'm';
                break;
            case 3600:
                $periodStr = 'h';
                break;
            default:
                $periodStr = $this->period . 's';
                break;
        }

        return "{$this->rate} req/{$periodStr} (burst {$this->burst})";
    }
}