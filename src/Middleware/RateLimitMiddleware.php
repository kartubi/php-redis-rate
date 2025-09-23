<?php

declare(strict_types=1);

namespace Fintar\RedisRate\Middleware;

use Closure;
use Fintar\RedisRate\Facades\RedisRate;
use Fintar\RedisRate\Limit;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class RateLimitMiddleware
{
    public function handle(Request $request, Closure $next, string $limit = 'api', ?string $key = null): Response
    {
        $key = $key ?: $this->resolveRequestKey($request);
        $limitConfig = $this->resolveLimit($limit);

        $result = RedisRate::allow($key, $limitConfig);

        if ($result->isExceeded()) {
            throw new TooManyRequestsHttpException(
                retryAfter: (int) ceil($result->getRetryAfterSeconds()),
                message: 'Too Many Requests'
            );
        }

        $response = $next($request);

        return $this->addRateLimitHeaders($response, $result);
    }

    protected function resolveRequestKey(Request $request): string
    {
        $ip = $request->ip();
        $route = $request->route()?->getName() ?: $request->getPathInfo();

        return "request:{$ip}:{$route}";
    }

    protected function resolveLimit(string $limit): Limit
    {
        if (is_numeric($limit)) {
            return Limit::perMinute((int) $limit);
        }

        $config = config("redis-rate.limits.{$limit}");

        if ($config) {
            return Limit::custom(
                rate: $config['rate'],
                burst: $config['burst'] ?? $config['rate'],
                periodInSeconds: $config['period']
            );
        }

        return match ($limit) {
            'api' => Limit::perMinute(60),
            'login' => Limit::custom(5, 5, 300),
            'upload' => Limit::custom(10, 20, 3600),
            default => Limit::perMinute(60),
        };
    }

    protected function addRateLimitHeaders(Response $response, $result): Response
    {
        $response->headers->set('X-RateLimit-Limit', (string) $result->limit->rate);
        $response->headers->set('X-RateLimit-Remaining', (string) $result->remaining);
        $response->headers->set('X-RateLimit-Reset', (string) (time() + (int) ceil($result->getResetAfterSeconds())));

        if ($result->isExceeded()) {
            $response->headers->set('Retry-After', (string) (int) ceil($result->getRetryAfterSeconds()));
        }

        return $response;
    }
}