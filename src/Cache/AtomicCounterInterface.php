<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Cache;

use FlintPHP\Framework\Cache\Exception\CacheException;
use FlintPHP\Framework\Cache\Exception\InvalidArgumentException;

/**
 * Capability contract for cache drivers that support atomic counter operations.
 */
interface AtomicCounterInterface
{
    /**
     * Atomically increment a counter and return the new value.
     *
     * If the key does not exist or has expired, it is created with value $step
     * and the given $ttl (if specified).
     *
     * If the key already exists, its value is incremented by $step WITHOUT
     * modifying or resetting the existing TTL/expiration time.
     *
     * @param string   $key  Cache key
     * @param int      $step Increment step (must be >= 1)
     * @param int|null $ttl  TTL in seconds for newly initialized counters
     * @return int The new counter value after increment
     *
     * @throws InvalidArgumentException If the key or step is invalid
     * @throws CacheException If the storage operation fails
     */
    public function increment(string $key, int $step = 1, ?int $ttl = null): int;
}
