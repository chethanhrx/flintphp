<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Cache;

use FlintPHP\Framework\Cache\Exception\InvalidArgumentException;
use FlintPHP\Framework\Cache\Exception\InvalidCacheValueException;
use FlintPHP\Framework\Database\ConnectionInterface;
use JsonException;
use PDO;

final class DatabaseCache implements CacheInterface, AtomicCounterInterface
{
    private const TABLE = 'flint_cache';

    public function __construct(private readonly ConnectionInterface $connection)
    {
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

    /**
     * Atomically increment a numeric cache value in the database.
     *
     * A single database upsert performs the read/modify/write operation.
     * Existing TTLs are preserved; expired or missing keys are initialized.
     */
    public function increment(string $key, int $step = 1, ?int $ttl = null): int
    {
        $this->validateKey($key);
        if ($step < 1) {
            throw new InvalidArgumentException('Increment step must be at least 1.');
        }
        if ($ttl !== null && $ttl <= 0) {
            throw new InvalidArgumentException('Initial TTL must be greater than 0.');
        }

        $now = time();
        $expiresAt = $ttl === null ? null : $now + $ttl;
        $initialValue = json_encode($step, JSON_THROW_ON_ERROR);
        $driver = (string) $this->connection->pdo()->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $sql = 'INSERT INTO ' . self::TABLE . ' (cache_key, value, expires_at) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    value = CASE
                        WHEN expires_at IS NOT NULL AND expires_at < ? THEN VALUES(value)
                        ELSE CAST(CAST(value AS SIGNED) + ? AS CHAR)
                    END,
                    expires_at = CASE
                        WHEN expires_at IS NOT NULL AND expires_at < ? THEN VALUES(expires_at)
                        ELSE expires_at
                    END';
        } elseif ($driver === 'pgsql' || $driver === 'sqlite') {
            $sql = 'INSERT INTO ' . self::TABLE . ' (cache_key, value, expires_at) VALUES (?, ?, ?)
                ON CONFLICT(cache_key) DO UPDATE SET
                    value = CASE
                        WHEN ' . self::TABLE . '.expires_at IS NOT NULL AND ' . self::TABLE . '.expires_at < ? THEN excluded.value
                        ELSE CAST(CAST(' . self::TABLE . '.value AS INTEGER) + ? AS TEXT)
                    END,
                    expires_at = CASE
                        WHEN ' . self::TABLE . '.expires_at IS NOT NULL AND ' . self::TABLE . '.expires_at < ? THEN excluded.expires_at
                        ELSE ' . self::TABLE . '.expires_at
                    END';
        } else {
            throw new InvalidArgumentException(sprintf('Unsupported database driver for atomic cache increments: %s', $driver));
        }

        return $this->connection->transaction(function () use (
            $sql,
            $key,
            $initialValue,
            $expiresAt,
            $now,
            $step
        ): int {
            $this->connection->execute($sql, [$key, $initialValue, $expiresAt, $now, $step, $now]);
            $stored = $this->connection->fetchColumn(
                'SELECT value FROM ' . self::TABLE . ' WHERE cache_key = ?',
                [$key]
            );

            if (!is_string($stored)) {
                throw new InvalidArgumentException('Atomic cache increment did not return a stored value.');
            }

            try {
                $value = json_decode($stored, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                throw new InvalidArgumentException('Stored cache counter is not valid JSON.', 0, $e);
            }

            if (!is_int($value)) {
                if (!is_numeric($value)) {
                    throw new InvalidArgumentException('Stored cache counter is not numeric.');
                }
                $value = (int) $value;
            }

            return $value;
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
