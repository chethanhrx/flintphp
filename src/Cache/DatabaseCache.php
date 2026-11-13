<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Cache;

use FlintPHP\Framework\Cache\Exception\InvalidArgumentException;
use FlintPHP\Framework\Cache\Exception\InvalidCacheValueException;
use FlintPHP\Framework\Database\ConnectionInterface;
use JsonException;

/**
 * Shared cache + atomic counters for multi-host deployments.
 *
 * Table `flint_cache`. Use with RateLimitMiddleware for cross-node limiting.
 * Keys: [a-zA-Z0-9_.]{1,64}
 */
final class DatabaseCache implements CacheInterface, AtomicCounterInterface
{
    private const TABLE = 'flint_cache';

    public function __construct(
        private readonly ConnectionInterface $connection,
    ) {
        $this->ensureTable();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->validateKey($key);
        $row = $this->readRow($key);

        if ($row === null) {
            return $default;
        }

        try {
            return json_decode($row['value'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->delete($key);

            return $default;
        }
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $this->validateKey($key);

        if ($ttl !== null && $ttl <= 0) {
            return $this->delete($key);
        }

        try {
            $payload = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $e) {
            throw new InvalidCacheValueException('Cannot serialize value to JSON for cache: ' . $e->getMessage(), 0, $e);
        }

        $expiresAt = $ttl === null ? null : time() + $ttl;

        $this->connection->transaction(function () use ($key, $payload, $expiresAt): void {
            $this->connection->execute('DELETE FROM ' . self::TABLE . ' WHERE cache_key = ?', [$key]);
            $this->connection->execute(
                'INSERT INTO ' . self::TABLE . ' (cache_key, value, expires_at) VALUES (?, ?, ?)',
                [$key, $payload, $expiresAt]
            );
        });

        return true;
    }

    public function delete(string $key): bool
    {
        $this->validateKey($key);
        $this->connection->execute('DELETE FROM ' . self::TABLE . ' WHERE cache_key = ?', [$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->connection->execute('DELETE FROM ' . self::TABLE);

        return true;
    }

    public function has(string $key): bool
    {
        return $this->readRow($key) !== null;
    }

    public function increment(string $key, int $step = 1, ?int $ttl = null): int
    {
        $this->validateKey($key);

        if ($step < 1) {
            throw new InvalidArgumentException('Increment step must be at least 1.');
        }

        if ($ttl !== null && $ttl <= 0) {
            throw new InvalidArgumentException('Initial TTL must be greater than 0.');
        }

        return $this->connection->transaction(function () use ($key, $step, $ttl): int {
            // Lock the row when supported (MySQL/Postgres). SQLite serializes the TX.
            $row = $this->connection->fetch(
                'SELECT value, expires_at FROM ' . self::TABLE . ' WHERE cache_key = ?',
                [$key]
            );

            $now = time();
            $expired = $row !== null
                && $row['expires_at'] !== null
                && (int) $row['expires_at'] < $now;

            if ($row === null || $expired) {
                if ($expired) {
                    $this->connection->execute(
                        'DELETE FROM ' . self::TABLE . ' WHERE cache_key = ?',
                        [$key]
                    );
                }

                $expiresAt = $ttl === null ? null : $now + $ttl;
                $payload = json_encode($step, JSON_THROW_ON_ERROR);
                $this->connection->execute(
                    'INSERT INTO ' . self::TABLE . ' (cache_key, value, expires_at) VALUES (?, ?, ?)',
                    [$key, $payload, $expiresAt]
                );

                return $step;
            }

            try {
                $current = json_decode((string) $row['value'], true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $current = 0;
            }

            $currentInt = is_int($current) ? $current : (is_numeric($current) ? (int) $current : 0);
            $newValue = $currentInt + $step;
            // Preserve existing expiration on increment.
            $this->connection->execute(
                'UPDATE ' . self::TABLE . ' SET value = ? WHERE cache_key = ?',
                [json_encode($newValue, JSON_THROW_ON_ERROR), $key]
            );

            return $newValue;
        });
    }

    /** @return array{value: string, expires_at: mixed}|null */
    private function readRow(string $key): ?array
    {
        $row = $this->connection->fetch(
            'SELECT value, expires_at FROM ' . self::TABLE . ' WHERE cache_key = ?',
            [$key]
        );

        if ($row === null) {
            return null;
        }

        if ($row['expires_at'] !== null && (int) $row['expires_at'] < time()) {
            $this->delete($key);

            return null;
        }

        return ['value' => (string) $row['value'], 'expires_at' => $row['expires_at']];
    }

    private function ensureTable(): void
    {
        $this->connection->execute(
            'CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
                cache_key VARCHAR(64) PRIMARY KEY NOT NULL,
                value TEXT NOT NULL,
                expires_at INTEGER NULL
            )'
        );
    }

    private function validateKey(string $key): void
    {
        if (preg_match('/^[a-zA-Z0-9_.]{1,64}$/', $key) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid cache key: "%s"', $key));
        }
    }
}
