<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Database\Migration;

use FlintPHP\Framework\Database\ConnectionInterface;
use InvalidArgumentException;
use RuntimeException;

/**
 * Runs migrations in order. Tracks applied ids in `flint_migrations`.
 *
 * Explicit: you pass MigrationInterface instances; nothing is auto-discovered.
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
     */
    public function migrate(array $migrations): array
    {
        $this->ensureTable();
        $applied = $this->appliedIds();
        $ran = [];

        $sorted = $migrations;
        usort($sorted, static fn (MigrationInterface $a, MigrationInterface $b): int => strcmp($a->id(), $b->id()));

        foreach ($sorted as $migration) {
            if (!$migration instanceof MigrationInterface) {
                throw new InvalidArgumentException('Migrations must implement MigrationInterface.');
            }

            $id = $migration->id();
            $this->assertSafeId($id);

            if (isset($applied[$id])) {
                continue;
            }

            $this->connection->transaction(function () use ($migration, $id): void {
                $migration->up($this->connection);
                $this->connection->execute(
                    'INSERT INTO ' . self::TABLE . ' (id, applied_at) VALUES (?, ?)',
                    [$id, gmdate('c')]
                );
            });

            $ran[] = $id;
            $applied[$id] = true;
        }

        return $ran;
    }

    /**
     * Roll back the last N applied migrations that appear in $migrations.
     *
     * @param list<MigrationInterface> $migrations
     * @return list<string> Rolled-back ids (newest first)
     */
    public function rollback(array $migrations, int $steps = 1): array
    {
        if ($steps < 1) {
            throw new InvalidArgumentException('steps must be >= 1');
        }

        $this->ensureTable();
        $byId = [];
        foreach ($migrations as $migration) {
            if (!$migration instanceof MigrationInterface) {
                throw new InvalidArgumentException('Migrations must implement MigrationInterface.');
            }
            $byId[$migration->id()] = $migration;
        }

        $rows = $this->connection->fetchAll(
            'SELECT id FROM ' . self::TABLE . ' ORDER BY id DESC'
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

    /** @return list<string> */
    public function status(array $migrations): array
    {
        $this->ensureTable();
        $applied = $this->appliedIds();
        $out = [];

        foreach ($migrations as $migration) {
            $id = $migration->id();
            $out[] = sprintf('%s %s', isset($applied[$id]) ? '[x]' : '[ ]', $id);
        }

        return $out;
    }

    private function ensureTable(): void
    {
        $this->connection->execute(
            'CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
                id TEXT PRIMARY KEY NOT NULL,
                applied_at TEXT NOT NULL
            )'
        );
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
        if ($id === '' || preg_match('/\A[a-zA-Z0-9_\-]+\z/', $id) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid migration id: %s', $id));
        }
    }
}
