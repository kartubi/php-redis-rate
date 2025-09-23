# PHP Redis Rate Limiter

A Laravel package for Redis-based rate limiting using the GCRA (Generic Cell Rate Algorithm) algorithm, compatible with Laravel 10, 11, and 12. This package is inspired by and compatible with the [go-redis/redis_rate](https://github.com/go-redis/redis_rate) library.

## Features

- **GCRA Algorithm**: Uses the Generic Cell Rate Algorithm (aka leaky bucket) for precise rate limiting
- **Laravel Integration**: Built specifically for Laravel with service providers, facades, and middleware
- **Redis Lua Scripts**: Atomic operations using Redis Lua scripts for consistency
- **Flexible Limits**: Support for per-second, per-minute, per-hour, and custom rate limits
- **Burst Support**: Configure burst capacity independently from rate limits
- **Laravel 11/12 Compatible**: Fully tested with the latest Laravel versions
- **Middleware Support**: Ready-to-use middleware for HTTP rate limiting
- **Artisan Commands**: Built-in testing commands for debugging rate limits

## Installation

Install the package via Composer:

```bash
composer require kartubi/php-redis-rate
```

The package will automatically register itself via Laravel's package discovery.

Publish the configuration file (optional):

```bash
php artisan vendor:publish --tag=redis-rate-config
```

## Requirements

- PHP 8.1 or higher
- Laravel 10.x, 11.x, or 12.x
- Redis 3.2 or newer (requires `replicate_commands` feature)
- Either the Redis PHP extension or Predis

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

## Laravel Version Compatibility

| Package Version | Laravel Version | PHP Version |
|-----------------|-----------------|-------------|
| 1.x             | 10.x, 11.x, 12.x | 8.1+        |

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