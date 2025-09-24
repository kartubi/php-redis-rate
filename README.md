# PHP Redis Rate Limiter

A modern Laravel package for Redis-based rate limiting using the GCRA (Generic Cell Rate Algorithm) algorithm. Built for Laravel 10+ with PHP 8.1+ features. Inspired by and compatible with the [go-redis/redis_rate](https://github.com/go-redis/redis_rate) library.

> **Looking for legacy Laravel support?** Check out the [v1.x branch](https://github.com/kartubi/php-redis-rate/tree/v1.x) for Laravel 5.5-9.x compatibility.

## Features

- **GCRA Algorithm**: Uses the Generic Cell Rate Algorithm (aka leaky bucket) for precise rate limiting
- **Laravel Integration**: Built specifically for Laravel with service providers, facades, and middleware
- **Redis Lua Scripts**: Atomic operations using Redis Lua scripts for consistency
- **Flexible Limits**: Support for per-second, per-minute, per-hour, and custom rate limits
- **Burst Support**: Configure burst capacity independently from rate limits
- **Modern PHP Features**: Uses PHP 8.1+ features like readonly properties, named parameters, and match expressions
- **Middleware Support**: Ready-to-use middleware for HTTP rate limiting
- **Artisan Commands**: Built-in testing commands for debugging rate limits

## Installation

Install the package via Composer:

```bash
composer require kartubi/php-redis-rate
```

> **For legacy Laravel projects (5.5-9.x):** Use `composer require kartubi/php-redis-rate:^1.0` instead.

The package will automatically register itself via Laravel's package discovery.

Publish the configuration file (optional):

```bash
php artisan vendor:publish --tag=redis-rate-config
```

### Clean Redis Keys Setup (Recommended)

By default, Laravel adds an app-name prefix to Redis keys (e.g., `laravel-app-database-rate:user123`). For cleaner rate limiting keys, run the setup command:

```bash
php artisan redis-rate:setup
```

This command will:
- ✅ Add a dedicated `rate_limiter` Redis connection to your `config/database.php`
- ✅ Use a separate Redis database (default: database 2)
- ✅ Remove Laravel's app-name prefix for rate limiting keys
- ✅ Result in clean keys like: `rate:user123`

You can also specify a custom database number:
```bash
php artisan redis-rate:setup --database=3
```

### Environment Configuration

After running setup, you can optionally configure these environment variables:

```env
# Redis database for rate limiting (optional, defaults to 2)
REDIS_RATE_DB=2

# Custom Redis connection for rate limiting (optional, auto-detected)
REDIS_RATE_CONNECTION=rate_limiter

# Custom key prefix (optional, defaults to 'rate:')
REDIS_RATE_PREFIX=rate:
```

### Docker Configuration

For Docker environments, add these environment variables to your `docker-compose.yml`:

```yaml
services:
  your-laravel-app:
    environment:
      # Standard Redis connection
      - REDIS_HOST=redis
      - REDIS_PORT=6379
      - REDIS_PASSWORD=null
      - REDIS_DB=0

      # Redis Rate Limiter (clean keys)
      - REDIS_RATE_DB=2
      - REDIS_RATE_CONNECTION=rate_limiter
      - REDIS_RATE_PREFIX=rate
```

The setup command will run automatically in your Dockerfile:
```dockerfile
RUN php artisan redis-rate:setup --force --database=2 || true
```

## Requirements

- PHP 8.1 or higher
- Laravel 10.x, 11.x, or 12.x
- Redis 3.2 or newer (requires `replicate_commands` feature)
- Either the Redis PHP extension or Predis 2.0+

## Basic Usage

### Using the Facade

```php
use Kartubi\RedisRate\Facades\RedisRate;
use Kartubi\RedisRate\Limit;

// Allow 10 requests per second
$result = RedisRate::allow('user:123', Limit::perSecond(10));

if ($result->isAllowed()) {
    // Request is allowed
    echo "Request allowed. Remaining: " . $result->remaining;
} else {
    // Rate limit exceeded
    echo "Rate limit exceeded. Retry after: " . $result->getRetryAfterSeconds() . " seconds";
}
```

### Using Dependency Injection

```php
use Kartubi\RedisRate\RedisRateLimiter;
use Kartubi\RedisRate\Limit;

class ApiController extends Controller
{
    public function __construct(
        private RedisRateLimiter $rateLimiter
    ) {}

    public function handle(Request $request)
    {
        $result = $this->rateLimiter->allow(
            'api:' . $request->ip(),
            Limit::perMinute(60)
        );

        if ($result->isExceeded()) {
            abort(429, 'Too Many Requests');
        }

        // Handle the request...
    }
}
```

## Rate Limit Types

### Pre-defined Limits

```php
// 10 requests per second (burst: 10)
$limit = Limit::perSecond(10);

// 60 requests per minute (burst: 60)
$limit = Limit::perMinute(60);

// 1000 requests per hour (burst: 1000)
$limit = Limit::perHour(1000);
```

### Custom Limits

```php
// 100 requests per minute with burst capacity of 150
$limit = Limit::custom(
    rate: 100,           // requests per period
    burst: 150,          // burst capacity
    periodInSeconds: 60  // period in seconds
);
```

## Advanced Usage

### Multiple Requests

```php
// Allow up to N requests at once
$result = RedisRate::allowN('bulk:user:123', Limit::perSecond(10), 5);

// Allow at most N requests (partial consumption)
$result = RedisRate::allowAtMost('batch:user:123', Limit::perSecond(10), 8);
```

### Reset Rate Limits

```php
// Reset all limits for a specific key
RedisRate::reset('user:123');
```

## Middleware Usage

Add the middleware to your HTTP kernel or use it directly in routes:

```php
// In routes/api.php
Route::middleware('redis-rate:api')->group(function () {
    Route::get('/users', [UserController::class, 'index']);
});

// With custom limits
Route::middleware('redis-rate:60,api')->get('/search', [SearchController::class, 'search']);

// With custom key
Route::middleware('redis-rate:login,auth:login')->post('/login', [AuthController::class, 'login']);
```

### Register Middleware

In your `app/Http/Kernel.php`:

```php
protected $routeMiddleware = [
    // ...
    'redis-rate' => \Kartubi\RedisRate\Middleware\RateLimitMiddleware::class,
];
```

## Configuration

The configuration file allows you to customize default settings:

```php
return [
    // Default Redis connection (null uses default)
    'connection' => env('REDIS_RATE_CONNECTION'),

    // Key prefix for Redis keys
    'key_prefix' => env('REDIS_RATE_PREFIX', 'rate:'),

    // Pre-defined rate limits
    'limits' => [
        'api' => [
            'rate' => 60,
            'period' => 60,
            'burst' => 10,
        ],
        'login' => [
            'rate' => 5,
            'period' => 300, // 5 minutes
            'burst' => 5,
        ],
        'upload' => [
            'rate' => 10,
            'period' => 3600, // 1 hour
            'burst' => 20,
        ],
    ],
];
```

## Testing Commands

Test your rate limiting configuration:

```bash
# Test with default settings
php artisan redis-rate:test "user:123"

# Test with custom parameters
php artisan redis-rate:test "api:test" --rate=10 --period=60 --burst=15 --requests=12
```

## Understanding Results

The `Result` object contains important information about the rate limiting decision:

```php
$result = RedisRate::allow('key', $limit);

// Check if request is allowed
$result->isAllowed();    // true if allowed
$result->isExceeded();   // true if rate limit exceeded

// Get remaining capacity
$result->remaining;      // Number of requests remaining

// Get timing information
$result->getRetryAfterSeconds();  // Seconds until next request allowed
$result->getResetAfterSeconds();  // Seconds until rate limit resets

// Get the limit that was applied
$result->limit;          // The Limit object used
```

## Algorithm Details

This package implements the GCRA (Generic Cell Rate Algorithm), also known as the leaky bucket algorithm. GCRA provides several advantages:

- **Smooth rate limiting**: Distributes requests evenly over time
- **Burst handling**: Allows controlled bursts while maintaining average rate
- **Memory efficient**: Uses minimal Redis memory per key
- **Atomic operations**: All operations are atomic using Redis Lua scripts

## Version Compatibility

| Package Version | Laravel Version | PHP Version | Branch |
|-----------------|-----------------|-------------|--------|
| **3.x** (Current) | 10.x, 11.x, 12.x | 8.1+ | `main` |
| 1.x (Legacy) | 5.5, 6.x, 7.x, 8.x, 9.x | 7.2+ | [`v1.x`](https://github.com/kartubi/php-redis-rate/tree/v1.x) |

> **Note:** This is the modern v3.x version. For legacy Laravel support, see the [v1.x branch documentation](https://github.com/kartubi/php-redis-rate/tree/v1.x).

## Testing

Run the test suite:

```bash
composer test
```

Run tests with coverage:

```bash
composer test-coverage
```

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## Credits

This package is inspired by the excellent [go-redis/redis_rate](https://github.com/go-redis/redis_rate) library and implements the same GCRA algorithm for PHP/Laravel applications.