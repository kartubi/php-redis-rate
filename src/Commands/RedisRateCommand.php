<?php

declare(strict_types=1);

namespace Kartubi\RedisRate\Commands;

use Kartubi\RedisRate\Facades\RedisRate;
use Kartubi\RedisRate\Limit;
use Illuminate\Console\Command;

class RedisRateCommand extends Command
{
    protected $signature = 'redis-rate:test
                          {key : The rate limit key to test}
                          {--rate=10 : Requests per period}
                          {--period=60 : Period in seconds}
                          {--burst= : Burst capacity (defaults to rate)}
                          {--requests=5 : Number of test requests}';

    protected $description = 'Test Redis rate limiting functionality';

    public function handle(): int
    {
        $key = $this->argument('key');
        $rate = (int) $this->option('rate');
        $period = (int) $this->option('period');
        $burst = $this->option('burst') ? (int) $this->option('burst') : $rate;
        $requests = (int) $this->option('requests');

        $limit = Limit::custom($rate, $burst, $period);

        $this->info("Testing rate limit: {$limit}");
        $this->info("Key: {$key}");
        $this->line('');

        for ($i = 1; $i <= $requests; $i++) {
            $result = RedisRate::allow($key, $limit);

            $status = $result->isAllowed() ? '<info>✓ ALLOWED</info>' : '<error>✗ DENIED</error>';

            $this->line("Request {$i}: {$status} (Remaining: {$result->remaining})");

            if ($result->isExceeded()) {
                $retryAfter = $result->getRetryAfterSeconds();
                $this->warn("  Rate limit exceeded. Retry after: {$retryAfter}s");
            }
        }

        $this->line('');
        $this->info('Test completed!');

        return 0;
    }
}