<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Database\Migration;

/**
 * Cross-process lock used to serialize migration runners.
 *
 * Implementations are connection/session scoped (MySQL GET_LOCK, Postgres advisory locks).
 * SQLite may no-op: single-writer file locking already serializes most local use.
 */
interface AdvisoryLockInterface
{
    /**
     * Block until the lock is acquired or $timeoutSeconds elapses.
     *
     * @throws MigrationLockException When the lock cannot be acquired in time
     */
    public function acquire(string $name, int $timeoutSeconds): void;

    /**
     * Release a previously acquired lock. Safe to call if the lock was not held.
     */
    public function release(string $name): void;
}
