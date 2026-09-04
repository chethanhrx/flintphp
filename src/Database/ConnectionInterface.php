<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Database;

use PDO;

interface ConnectionInterface
{
    /**
     * Execute an SQL statement and return the number of affected rows.
     *
     * @param string $sql
     * @param array<int|string, mixed> $parameters
     */
    public function execute(string $sql, array $parameters = []): int;

    /**
     * Execute an SQL statement and return all result rows as an associative array.
     *
     * @param string $sql
     * @param array<int|string, mixed> $parameters
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(string $sql, array $parameters = []): array;

    /**
     * Execute an SQL statement and return a single row as an associative array.
     *
     * @param string $sql
     * @param array<int|string, mixed> $parameters
     * @return array<string, mixed>|null
     */
    public function fetch(string $sql, array $parameters = []): ?array;

    /**
     * Execute an SQL statement and return a single scalar value from the first column.
     *
     * @param string $sql
     * @param array<int|string, mixed> $parameters
     */
    public function fetchColumn(string $sql, array $parameters = []): mixed;

    /**
     * Start a database transaction.
     */
    public function begin(): void;

    /**
     * Commit the active database transaction.
     */
    public function commit(): void;

    /**
     * Rollback the active database transaction.
     */
    public function rollBack(): void;

    /**
     * Determine if a database transaction is currently active.
     */
    public function inTransaction(): bool;

    /**
     * Execute a callback within a transaction.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed;

    /**
     * Get the underlying PDO instance.
     */
    public function pdo(): PDO;
}
