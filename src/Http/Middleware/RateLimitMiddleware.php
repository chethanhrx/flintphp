<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Http\Middleware;

use FlintPHP\Framework\Cache\CacheInterface;
use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;
use FlintPHP\Framework\Middleware\MiddlewareInterface;

/**
 * Fixed-window rate limiter backed by CacheInterface.
 *
 * Key: client IP by default. Returns 429 with Retry-After when exceeded.
 */
final class RateLimitMiddleware implements MiddlewareInterface
{
    /**
     * @param callable(Request): string|null $keyResolver
     */
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly int $maxAttempts = 60,
        private readonly int $windowSeconds = 60,
        private readonly mixed $keyResolver = null,
    ) {
        if ($this->maxAttempts < 1 || $this->windowSeconds < 1) {
            throw new \InvalidArgumentException('maxAttempts and windowSeconds must be >= 1');
        }
    }

    public function process(Request $request, callable $next): Response
    {
        $key = $this->resolveKey($request);
        $cacheKey = 'rate:' . hash('sha256', $key);

        $current = $this->cache->get($cacheKey, 0);
        if (!is_int($current) && !is_float($current) && !is_string($current)) {
            $current = 0;
        }
        $current = (int) $current;

        if ($current >= $this->maxAttempts) {
            return Response::error('Too Many Requests', 429, 'rate_limited')
                ->withHeader('Retry-After', (string) $this->windowSeconds)
                ->withHeader('X-RateLimit-Limit', (string) $this->maxAttempts)
                ->withHeader('X-RateLimit-Remaining', '0');
        }

        $this->cache->set($cacheKey, $current + 1, $this->windowSeconds);

        $response = $next($request);
        $remaining = max(0, $this->maxAttempts - ($current + 1));

        return $response
            ->withHeader('X-RateLimit-Limit', (string) $this->maxAttempts)
            ->withHeader('X-RateLimit-Remaining', (string) $remaining);
    }

    private function resolveKey(Request $request): string
    {
        if (is_callable($this->keyResolver)) {
            return (string) ($this->keyResolver)($request);
        }

        return $request->clientIp() ?? 'unknown';
    }
}
