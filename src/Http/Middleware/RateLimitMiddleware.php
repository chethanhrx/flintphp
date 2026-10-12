<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Http\Middleware;

use FlintPHP\Framework\Cache\AtomicCounterInterface;
use FlintPHP\Framework\Cache\CacheInterface;
use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;
use FlintPHP\Framework\Middleware\MiddlewareInterface;
use InvalidArgumentException;
use Throwable;

/**
 * Concurrency-safe fixed-window rate limiter backed by CacheInterface.
 *
 * For concurrency safety across processes or distributed nodes, the cache driver
 * should implement AtomicCounterInterface (e.g. ArrayCache, FileCache).
 * If a non-atomic CacheInterface driver is supplied, a read-modify-write fallback
 * is used, but concurrency safety cannot be guaranteed by the driver.
 *
 * Cache keys obey framework rules: [a-zA-Z0-9_.]{1,64}
 */
final class RateLimitMiddleware implements MiddlewareInterface
{
    /**
     * @param callable(Request): (string|null) $keyResolver
     */
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly int $maxAttempts = 60,
        private readonly int $windowSeconds = 60,
        private readonly mixed $keyResolver = null,
        private readonly bool $failClosed = true,
    ) {
        if ($this->maxAttempts < 1) {
            throw new InvalidArgumentException('maxAttempts must be >= 1');
        }
        if ($this->windowSeconds < 1) {
            throw new InvalidArgumentException('windowSeconds must be >= 1');
        }
    }

    public function process(Request $request, callable $next): Response
    {
        $cacheKey = $this->cacheKey($this->resolveKey($request));

        try {
            if ($this->cache instanceof AtomicCounterInterface) {
                $current = $this->cache->increment($cacheKey, 1, $this->windowSeconds);
            } else {
                // Non-atomic fallback for basic CacheInterface drivers.
                $currentVal = $this->cache->get($cacheKey, 0);
                $current = is_numeric($currentVal) ? (int) $currentVal : 0;
                if ($current >= $this->maxAttempts) {
                    return $this->rateLimitResponse();
                }
                $current++;
                $this->cache->set($cacheKey, $current, $this->windowSeconds);
            }
        } catch (Throwable) {
            if ($this->failClosed) {
                return $this->rateLimitResponse();
            }

            return $next($request);
        }

        if ($current > $this->maxAttempts) {
            return $this->rateLimitResponse();
        }

        $response = $next($request);
        $remaining = max(0, $this->maxAttempts - $current);

        return $response
            ->withHeader('X-RateLimit-Limit', (string) $this->maxAttempts)
            ->withHeader('X-RateLimit-Remaining', (string) $remaining);
    }

    private function rateLimitResponse(): Response
    {
        return Response::error('Too Many Requests', 429, 'rate_limited')
            ->withHeader('Retry-After', (string) $this->windowSeconds)
            ->withHeader('X-RateLimit-Limit', (string) $this->maxAttempts)
            ->withHeader('X-RateLimit-Remaining', '0');
    }

    private function resolveKey(Request $request): string
    {
        if (is_callable($this->keyResolver)) {
            $resolved = ($this->keyResolver)($request);
            if (is_string($resolved) && trim($resolved) !== '') {
                return $resolved;
            }
            return 'unknown';
        }

        return $request->clientIp() ?? 'unknown';
    }

    /**
     * Build a key compatible with ArrayCache/FileCache validation.
     */
    private function cacheKey(string $clientKey): string
    {
        // "rl." + 32 hex chars = 35 chars, within 64 and allowed charset [a-zA-Z0-9_.]
        return 'rl.' . substr(hash('sha256', $clientKey), 0, 32);
    }
}
