<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Database\Migration;

use FlintPHP\Framework\Database\ConnectionInterface;
use InvalidArgumentException;
use RuntimeException;

/**
 * Runs migrations in order and tracks applied ids in `flint_migrations`.
 *
 * Explicit: you pass MigrationInterface instances; nothing is auto-discovered.
 *
 * Rollback guarantees:
 * - Tracks an incrementing sequence number to guarantee rollback in exact reverse application order,
 *   even when migration IDs were lexical out-of-order or applied in rapid succession.
 * - Rejects duplicate migration IDs immediately to avoid skipped migrations.
 * - Transactions are used for atomic migration record keeping.
 *
 * Note on database engine behavior:
 * - PostgreSQL and SQLite support transactional DDL. If a migration's up() fails, schema changes
 *   are rolled back along with the tracking record.
 * - MySQL and MariaDB perform an implicit COMMIT on DDL statements (e.g. CREATE TABLE, ALTER TABLE).
 *   Therefore, failed DDL statements cannot be rolled back transactionally on MySQL/MariaDB.
 */
final class Migrator
{
    private const TABLE = 'flint_migrations';

    public function __construct(
        private readonly ConnectionInterface $connection,
    ) {
    }

    /**
     * @param list<MigrationInterface> $migrations
     * @return list<string> Applied migration ids
     *
     * @throws InvalidArgumentException On invalid or duplicate migration IDs
     */
    public function migrate(array $migrations): array
    {
        $this->ensureTable();
        $this->validateMigrations($migrations);

        $applied = $this->appliedIds();
        $ran = [];

        $sorted = $migrations;
        usort($sorted, static fn (MigrationInterface $a, MigrationInterface $b): int => strcmp($a->id(), $b->id()));

        $currentMaxSeq = (int) $this->connection->fetchColumn('SELECT COALESCE(MAX(sequence), 0) FROM ' . self::TABLE);

        foreach ($sorted as $migration) {
            $id = $migration->id();

            if (isset($applied[$id])) {
                continue;
            }

            $currentMaxSeq++;
            $seqToInsert = $currentMaxSeq;

            $this->connection->transaction(function () use ($migration, $id, $seqToInsert): void {
                $migration->up($this->connection);
                $this->connection->execute(
                    'INSERT INTO ' . self::TABLE . ' (id, applied_at, sequence) VALUES (?, ?, ?)',
                    [$id, gmdate('c'), $seqToInsert]
                );
            });

            $ran[] = $id;
            $applied[$id] = true;
        }

        return $ran;
    }

    /**
     * Roll back the last N applied migrations in exact reverse order of application.
     *
     * @param list<MigrationInterface> $migrations
     * @return list<string> Rolled-back ids (newest applied first)
     *
     * @throws InvalidArgumentException On invalid arguments
     * @throws RuntimeException If an applied migration has no matching implementation
     */
    public function rollback(array $migrations, int $steps = 1): array
    {
        if ($steps < 1) {
            throw new InvalidArgumentException('steps must be >= 1');
        }

        $this->ensureTable();
        $this->validateMigrations($migrations);

        $byId = [];
        foreach ($migrations as $migration) {
            $byId[$migration->id()] = $migration;
        }

        // Order by application sequence DESC, falling back to applied_at DESC and id DESC
        $rows = $this->connection->fetchAll(
            'SELECT id FROM ' . self::TABLE . ' ORDER BY sequence DESC, applied_at DESC, id DESC'
        );

        $rolled = [];
        foreach ($rows as $row) {
            if (count($rolled) >= $steps) {
                break;
            }

            $id = (string) $row['id'];
            if (!isset($byId[$id])) {
                throw new RuntimeException(sprintf(
                    'Cannot rollback migration "%s": implementation not provided.',
                    $id
                ));
            }

            $this->connection->transaction(function () use ($byId, $id): void {
                $byId[$id]->down($this->connection);
                $this->connection->execute(
                    'DELETE FROM ' . self::TABLE . ' WHERE id = ?',
                    [$id]
                );
            });

            $rolled[] = $id;
        }

        return $rolled;
    }

    /**
     * Report status of migrations: [x] applied, [ ] pending.
     *
     * @param list<MigrationInterface> $migrations
     * @return list<string>
     */
    public function status(array $migrations): array
    {
        $this->ensureTable();
        $this->validateMigrations($migrations);

        $applied = $this->appliedIds();
        $out = [];

        $sorted = $migrations;
        usort($sorted, static fn (MigrationInterface $a, MigrationInterface $b): int => strcmp($a->id(), $b->id()));

        foreach ($sorted as $migration) {
            $id = $migration->id();
            $out[] = sprintf('%s %s', isset($applied[$id]) ? '[x]' : '[ ]', $id);
        }

        return $out;
    }

    private function ensureTable(): void
    {
        $this->connection->execute(
            'CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
                id VARCHAR(191) PRIMARY KEY NOT NULL,
                applied_at TEXT NOT NULL,
                sequence INTEGER NOT NULL DEFAULT 0
            )'
        );

        // Safe upgrade path: add sequence column if existing table was created before sequence support
        try {
            $this->connection->execute('ALTER TABLE ' . self::TABLE . ' ADD COLUMN sequence INTEGER NOT NULL DEFAULT 0');
        } catch (\Throwable) {
            // Column already present; ignore error safely
        }
    }

    /**
     * Validate that all items implement MigrationInterface, have valid safe IDs, and are unique.
     *
     * @param list<MigrationInterface> $migrations
     * @throws InvalidArgumentException
     */
    private function validateMigrations(array $migrations): void
    {
        $seen = [];
        foreach ($migrations as $migration) {
            if (!$migration instanceof MigrationInterface) {
                throw new InvalidArgumentException('Migrations must implement MigrationInterface.');
            }

            $id = $migration->id();
            $this->assertSafeId($id);

            if (isset($seen[$id])) {
                throw new InvalidArgumentException(sprintf('Duplicate migration id detected: "%s"', $id));
            }

            $seen[$id] = true;
        }
    }

    /** @return array<string, true> */
    private function appliedIds(): array
    {
        $rows = $this->connection->fetchAll('SELECT id FROM ' . self::TABLE);
        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['id']] = true;
        }

        return $map;
    }

    private function assertSafeId(string $id): void
    {
        if ($id === '' || strlen($id) > 191 || preg_match('/\A[a-zA-Z0-9_\-]+\z/', $id) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid migration id: "%s"', $id));
        }
    }
}
