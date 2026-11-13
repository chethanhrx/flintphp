<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Session\Store;

use FlintPHP\Framework\Database\ConnectionInterface;
use FlintPHP\Framework\Session\SessionStoreInterface;
use JsonException;
use RuntimeException;

/**
 * Shared, durable session store for multi-host deployments.
 *
 * Uses table `flint_sessions`. Suitable for MySQL, PostgreSQL, and SQLite when
 * every application instance shares the same database.
 */
final class DatabaseSessionStore implements SessionStoreInterface
{
    private const TABLE = 'flint_sessions';

    public function __construct(
        private readonly ConnectionInterface $connection,
    ) {
        $this->ensureTable();
    }

    public function read(string $id): ?array
    {
        $this->assertValidId($id);

        $row = $this->connection->fetch(
            'SELECT payload, expires_at FROM ' . self::TABLE . ' WHERE id = ?',
            [$id]
        );

        if ($row === null) {
            return null;
        }

        $expiresAt = (int) $row['expires_at'];
        if ($expiresAt <= time()) {
            $this->delete($id);

            return null;
        }

        try {
            $data = json_decode((string) $row['payload'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!is_array($data)) {
            return null;
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    public function write(string $id, array $data, int $expiresAt): void
    {
        $this->assertValidId($id);

        if ($expiresAt <= time()) {
            $this->delete($id);

            return;
        }

        try {
            $payload = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new RuntimeException('Session data must be JSON-serializable.', 0, $exception);
        }

        // Portable upsert: delete then insert inside a transaction.
        $this->connection->transaction(function () use ($id, $payload, $expiresAt): void {
            $this->connection->execute('DELETE FROM ' . self::TABLE . ' WHERE id = ?', [$id]);
            $this->connection->execute(
                'INSERT INTO ' . self::TABLE . ' (id, payload, expires_at) VALUES (?, ?, ?)',
                [$id, $payload, $expiresAt]
            );
        });
    }

    public function delete(string $id): void
    {
        $this->assertValidId($id);
        $this->connection->execute('DELETE FROM ' . self::TABLE . ' WHERE id = ?', [$id]);
    }

    private function ensureTable(): void
    {
        $this->connection->execute(
            'CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
                id VARCHAR(64) PRIMARY KEY NOT NULL,
                payload TEXT NOT NULL,
                expires_at INTEGER NOT NULL
            )'
        );

        try {
            $this->connection->execute(
                'CREATE INDEX flint_sessions_expires_at ON ' . self::TABLE . ' (expires_at)'
            );
        } catch (\Throwable) {
            // Index may already exist.
        }
    }

    private function assertValidId(string $id): void
    {
        if (preg_match('/\A[a-f0-9]{64}\z/', $id) !== 1) {
            throw new RuntimeException('Invalid session ID.');
        }
    }
}
