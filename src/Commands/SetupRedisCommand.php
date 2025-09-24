<?php

declare(strict_types=1);

namespace Kartubi\RedisRate\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SetupRedisCommand extends Command
{
    protected $signature = 'redis-rate:setup
                          {--force : Force overwrite existing configuration}
                          {--database=2 : Redis database number for rate limiting}';

    protected $description = 'Setup dedicated Redis connection for rate limiting without app prefix';

    public function handle(): int
    {
        $configPath = config_path('database.php');

        if (!file_exists($configPath)) {
            $this->error("Database config file not found: {$configPath}");
            return 1;
        }

        $this->info('Setting up Redis Rate Limiter configuration...');

        // Read current config
        $config = include $configPath;

        // Check if rate_limiter connection already exists
        if (isset($config['redis']['rate_limiter']) && !$this->option('force')) {
            $this->warn('Redis rate_limiter connection already exists!');

            if (!$this->confirm('Do you want to overwrite it?')) {
                $this->info('Setup cancelled.');
                return 0;
            }
        }

        // Add the rate_limiter connection
        $database = $this->option('database');

        $rateLimiterConfig = [
            'url' => "env('REDIS_URL')",
            'host' => "env('REDIS_HOST', '127.0.0.1')",
            'username' => "env('REDIS_USERNAME')",
            'password' => "env('REDIS_PASSWORD')",
            'port' => "env('REDIS_PORT', '6379')",
            'database' => "env('REDIS_RATE_DB', '{$database}')",
            'max_retries' => "env('REDIS_MAX_RETRIES', 3)",
            'backoff_algorithm' => "env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter')",
            'backoff_base' => "env('REDIS_BACKOFF_BASE', 100)",
            'backoff_cap' => "env('REDIS_BACKOFF_CAP', 1000)",
        ];

        // Update the configuration in memory
        $config['redis']['rate_limiter'] = $rateLimiterConfig;

        // Write back to file
        if ($this->writeConfigFile($configPath, $config)) {
            $this->info('✓ Added rate_limiter connection to database config');
            $this->info("✓ Using Redis database: {$database}");
            $this->newLine();

            $this->info('You can now set these environment variables:');
            $this->line('  <comment>REDIS_RATE_DB=' . $database . '</comment> (optional, defaults to ' . $database . ')');
            $this->line('  <comment>REDIS_RATE_CONNECTION=rate_limiter</comment> (optional, will auto-detect)');

            $this->newLine();
            $this->info('Your rate limiting keys will now be clean: <info>rate:user123</info>');
            $this->info('Instead of: <error>laravel-app-database-rate:user123</error>');

            return 0;
        }

        $this->error('Failed to update database configuration file!');
        return 1;
    }

    private function writeConfigFile(string $path, array $config): bool
    {
        try {
            $content = "<?php\n\nuse Illuminate\Support\Str;\n\nreturn " . $this->arrayToPhp($config) . ";\n";

            return file_put_contents($path, $content) !== false;
        } catch (\Exception $e) {
            $this->error("Error writing config file: " . $e->getMessage());
            return false;
        }
    }

    private function arrayToPhp(array $array, int $indent = 0): string
    {
        $spaces = str_repeat('    ', $indent);
        $result = "[\n";

        foreach ($array as $key => $value) {
            $result .= $spaces . '    ';

            if (is_string($key)) {
                $result .= "'{$key}' => ";
            }

            if (is_array($value)) {
                $result .= $this->arrayToPhp($value, $indent + 1);
            } elseif (is_string($value)) {
                // Check if it's an env() call or other PHP code
                if (Str::startsWith($value, 'env(') ||
                    Str::contains($value, ['Str::', 'config(', 'database_path('])) {
                    $result .= $value;
                } else {
                    $result .= "'{$value}'";
                }
            } elseif (is_bool($value)) {
                $result .= $value ? 'true' : 'false';
            } elseif (is_null($value)) {
                $result .= 'null';
            } else {
                $result .= $value;
            }

            $result .= ",\n";
        }

        $result .= $spaces . ']';

        return $result;
    }
}