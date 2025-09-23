<?php

declare(strict_types=1);

namespace Kartubi\RedisRate\Middleware;

use Closure;
use Kartubi\RedisRate\Facades\RedisRate;
use Kartubi\RedisRate\Limit;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class RateLimitMiddleware
{
    public function handle(Request $request, Closure $next, string $limit = 'api', $key = null)
    {
        $key = $key ?: $this->resolveRequestKey($request);
        $limitConfig = $this->resolveLimit($limit);

        $result = RedisRate::allow($key, $limitConfig);

        if ($result->isExceeded()) {
            throw new TooManyRequestsHttpException(
                (int) ceil($result->getRetryAfterSeconds()),
                'Too Many Requests'
            );
        }

        $response = $next($request);

        return $this->addRateLimitHeaders($response, $result);
    }

    protected function resolveRequestKey(Request $request): string
    {
        $ip = $request->ip();
        $route = $request->route() ? $request->route()->getName() : $request->getPathInfo();

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
                $config['rate'],
                $config['burst'] ?? $config['rate'],
                $config['period']
            );
        }

        switch ($limit) {
            case 'api':
                return Limit::perMinute(60);
            case 'login':
                return Limit::custom(5, 5, 300);
            case 'upload':
                return Limit::custom(10, 20, 3600);
            default:
                return Limit::perMinute(60);
        }
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