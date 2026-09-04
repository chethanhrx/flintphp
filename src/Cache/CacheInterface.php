<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Cache;

use FlintPHP\Framework\Cache\Exception\CacheException;
use FlintPHP\Framework\Cache\Exception\InvalidArgumentException;

/**
 * Defines the contract for caching mechanisms.
 * 
 * The API mirrors PSR-16 (Simple Cache) to provide a standard interface.
 */
interface CacheInterface
{
    /**
     * Fetches a value from the cache.
     *
     * @param string $key     The unique key of this item in the cache.
     * @param mixed  $default Default value to return if the key does not exist.
     *
     * @return mixed The value of the item from the cache, or $default in case of cache miss.
     *
     * @throws InvalidArgumentException If the $key string is not a legal value.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
     *
     * @param string $key   The key of the item to store.
     * @param mixed  $value The value of the item to store, must be serializable.
     * @param int|null $ttl Optional. The TTL value of this item in seconds.
     *
     * @return bool True on success and false on failure.
     *
     * @throws InvalidArgumentException If the $key string is not a legal value.
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool;

    /**
     * Removes an item from the cache.
     *
     * @param string $key The key of the item to remove.
     *
     * @return bool True if the item was successfully removed. False if there was an error.
     *
     * @throws InvalidArgumentException If the $key string is not a legal value.
     */
    public function delete(string $key): bool;

    /**
     * Wipes clean the entire cache's keys.
     *
     * @return bool True on success and false on failure.
     */
    public function clear(): bool;

    /**
     * Determines whether an item is present in the cache.
     *
     * @param string $key The cache item key.
     *
     * @return bool
     *
     * @throws InvalidArgumentException If the $key string is not a legal value.
     */
    public function has(string $key): bool;
}
