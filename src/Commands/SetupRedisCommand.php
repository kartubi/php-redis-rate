<?php

declare(strict_types=1);

namespace Kartubi\RedisRate\Commands;

use Illuminate\Console\Command;

class SetupRedisCommand extends Command
{
    protected $signature = 'redis-rate:setup
                          {--force : Force overwrite existing configuration}
                          {--database=2 : Redis database number for rate limiting}';

    protected $description = 'Setup dedicated Redis configuration for clean rate limiting keys';

    public function handle(): int
    {
        $this->info('Setting up Redis Rate Limiter configuration...');

        // Publish the config file if it doesn't exist
        $configPath = config_path('redis-rate.php');

        if (!file_exists($configPath) || $this->option('force')) {
            $this->call('vendor:publish', [
                '--tag' => 'redis-rate-config',
                '--force' => $this->option('force')
            ]);
            $this->info('✓ Published redis-rate.php configuration file');
        } else {
            $this->info('✓ Configuration file already exists');
        }

        $database = $this->option('database');

        $this->newLine();
        $this->info('<fg=green>Setup Complete!</>');
        $this->line('Redis Rate Limiter is now configured with clean keys.');

        $this->newLine();
        $this->info('Environment variables you can set:');
        $this->line("  <comment>REDIS_RATE_DB={$database}</comment> (Redis database for rate limiting)");
        $this->line('  <comment>REDIS_RATE_HOST=127.0.0.1</comment> (Redis host)');
        $this->line('  <comment>REDIS_RATE_PORT=6379</comment> (Redis port)');
        $this->line('  <comment>REDIS_RATE_PASSWORD=null</comment> (Redis password)');
        $this->line('  <comment>REDIS_RATE_PREFIX=rate:</comment> (Key prefix)');

        $this->newLine();
        $this->info('Your rate limiting keys will be clean: <info>rate:user123</info>');
        $this->info('No more: <error>laravel-app-database-rate:user123</error>');

        $this->newLine();
        $this->info('The rate limiter uses its own Redis connection - no changes to config/database.php needed!');

        return 0;
    }
}