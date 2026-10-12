<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Database;

use FlintPHP\Framework\Database\ConnectionFactory;
use FlintPHP\Framework\Database\ConnectionInterface;
use FlintPHP\Framework\Database\Migration\MigrationInterface;
use FlintPHP\Framework\Database\Migration\Migrator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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
}
