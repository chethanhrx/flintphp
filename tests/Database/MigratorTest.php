<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Database;

use FlintPHP\Framework\Database\ConnectionFactory;
use FlintPHP\Framework\Database\ConnectionInterface;
use FlintPHP\Framework\Database\Migration\MigrationInterface;
use FlintPHP\Framework\Database\Migration\Migrator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MigratorTest extends TestCase
{
    private ConnectionInterface $db;
    private Migrator $migrator;

    protected function setUp(): void
    {
        $this->db = ConnectionFactory::make(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->migrator = new Migrator($this->db);
    }

    #[Test]
    public function migrate_runs_pending_once(): void
    {
        $m = new class implements MigrationInterface {
            public function id(): string { return '20260101000000_users'; }
            public function up(ConnectionInterface $c): void {
                $c->execute('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
            }
            public function down(ConnectionInterface $c): void {
                $c->execute('DROP TABLE users');
            }
        };

        $this->assertSame(['20260101000000_users'], $this->migrator->migrate([$m]));
        $this->assertSame([], $this->migrator->migrate([$m]));

        $this->db->execute('INSERT INTO users (name) VALUES (?)', ['Ada']);
        $this->assertSame(1, $this->db->fetchColumn('SELECT COUNT(*) FROM users'));
    }

    #[Test]
    public function rollback_reverts_last(): void
    {
        $m = new class implements MigrationInterface {
            public function id(): string { return '20260101000001_items'; }
            public function up(ConnectionInterface $c): void {
                $c->execute('CREATE TABLE items (id INTEGER PRIMARY KEY)');
            }
            public function down(ConnectionInterface $c): void {
                $c->execute('DROP TABLE items');
            }
        };

        $this->migrator->migrate([$m]);
        $this->assertSame(['20260101000001_items'], $this->migrator->rollback([$m], 1));
    }

    #[Test]
    public function rejects_duplicate_migration_ids_in_migrate(): void
    {
        $m1 = new class implements MigrationInterface {
            public function id(): string { return '20260101000001_dupe'; }
            public function up(ConnectionInterface $c): void {}
            public function down(ConnectionInterface $c): void {}
        };
        $m2 = new class implements MigrationInterface {
            public function id(): string { return '20260101000001_dupe'; }
            public function up(ConnectionInterface $c): void {}
            public function down(ConnectionInterface $c): void {}
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate migration id detected: "20260101000001_dupe"');

        $this->migrator->migrate([$m1, $m2]);
    }

    #[Test]
    public function rejects_invalid_migration_id(): void
    {
        $invalid = new class implements MigrationInterface {
            public function id(): string { return 'invalid/id;DROP TABLE users'; }
            public function up(ConnectionInterface $c): void {}
            public function down(ConnectionInterface $c): void {}
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid migration id');

        $this->migrator->migrate([$invalid]);
    }

    #[Test]
    public function rollback_reverts_in_actual_application_order_not_lexical(): void
    {
        // Suppose migration B was applied in an earlier run, then migration A (with older lexical ID)
        // was applied in a later run (e.g. branch merge).
        $mB = new class implements MigrationInterface {
            public function id(): string { return '20260201000000_b'; }
            public function up(ConnectionInterface $c): void {
                $c->execute('CREATE TABLE tbl_b (id INTEGER PRIMARY KEY)');
            }
            public function down(ConnectionInterface $c): void {
                $c->execute('DROP TABLE tbl_b');
            }
        };

        $mA = new class implements MigrationInterface {
            public function id(): string { return '20260101000000_a'; }
            public function up(ConnectionInterface $c): void {
                $c->execute('CREATE TABLE tbl_a (id INTEGER PRIMARY KEY)');
            }
            public function down(ConnectionInterface $c): void {
                $c->execute('DROP TABLE tbl_a');
            }
        };

        // First apply B
        $this->migrator->migrate([$mB]);

        // Next apply A in a subsequent run
        $this->migrator->migrate([$mA]);

        // When rolling back 1 step, the last applied migration was mA (even though its ID is lexically smaller)
        $rolled1 = $this->migrator->rollback([$mA, $mB], 1);
        $this->assertSame(['20260101000000_a'], $rolled1);

        // Rolling back the remaining step reverts mB
        $rolled2 = $this->migrator->rollback([$mA, $mB], 1);
        $this->assertSame(['20260201000000_b'], $rolled2);
    }

    #[Test]
    public function partial_failure_rolls_back_transaction_and_does_not_mark_applied(): void
    {
        $failing = new class implements MigrationInterface {
            public function id(): string { return '20260301000000_failing'; }
            public function up(ConnectionInterface $c): void {
                $c->execute('CREATE TABLE test_fail (id INTEGER PRIMARY KEY)');
                throw new RuntimeException('Syntax or logic error in migration');
            }
            public function down(ConnectionInterface $c): void {}
        };

        try {
            $this->migrator->migrate([$failing]);
            $this->fail('Expected exception was not thrown');
        } catch (RuntimeException $e) {
            $this->assertSame('Syntax or logic error in migration', $e->getMessage());
        }

        // Must not be tracked in flint_migrations
        $count = $this->db->fetchColumn('SELECT COUNT(*) FROM flint_migrations WHERE id = ?', ['20260301000000_failing']);
        $this->assertSame(0, (int) $count);
    }

    #[Test]
    public function rollback_throws_when_migration_implementation_is_missing(): void
    {
        $m = new class implements MigrationInterface {
            public function id(): string { return '20260101000000_installed'; }
            public function up(ConnectionInterface $c): void {}
            public function down(ConnectionInterface $c): void {}
        };

        $this->migrator->migrate([$m]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot rollback migration "20260101000000_installed": implementation not provided');

        // Rollback called without passing the installed migration
        $this->migrator->rollback([], 1);
    }

    #[Test]
    public function backward_compatibility_with_existing_table_without_sequence_column(): void
    {
        // Manually create old schema without sequence column
        $this->db->execute('CREATE TABLE flint_migrations (id TEXT PRIMARY KEY NOT NULL, applied_at TEXT NOT NULL)');
        $this->db->execute('INSERT INTO flint_migrations (id, applied_at) VALUES (?, ?)', ['20250101000000_legacy', '2025-01-01T00:00:00+00:00']);

        $mLegacy = new class implements MigrationInterface {
            public function id(): string { return '20250101000000_legacy'; }
            public function up(ConnectionInterface $c): void {}
            public function down(ConnectionInterface $c): void {}
        };

        $mNew = new class implements MigrationInterface {
            public function id(): string { return '20260101000000_new'; }
            public function up(ConnectionInterface $c): void {}
            public function down(ConnectionInterface $c): void {}
        };

        // Migrator should auto-upgrade table without error and successfully run new migration
        $ran = $this->migrator->migrate([$mLegacy, $mNew]);
        $this->assertSame(['20260101000000_new'], $ran);

        // Rollback should revert new migration first
        $rolled = $this->migrator->rollback([$mLegacy, $mNew], 1);
        $this->assertSame(['20260101000000_new'], $rolled);
    }

    #[Test]
    public function status_reports_applied_and_pending_migrations(): void
    {
        $m1 = new class implements MigrationInterface {
            public function id(): string { return '20260101000001_one'; }
            public function up(ConnectionInterface $c): void {}
            public function down(ConnectionInterface $c): void {}
        };
        $m2 = new class implements MigrationInterface {
            public function id(): string { return '20260101000002_two'; }
            public function up(ConnectionInterface $c): void {}
            public function down(ConnectionInterface $c): void {}
        };

        $this->migrator->migrate([$m1]);

        $status = $this->migrator->status([$m1, $m2]);
        $this->assertSame([
            '[x] 20260101000001_one',
            '[ ] 20260101000002_two',
        ], $status);
    }
}
