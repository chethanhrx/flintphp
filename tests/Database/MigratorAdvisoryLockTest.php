<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Database;

use FlintPHP\Framework\Database\ConnectionFactory;
use FlintPHP\Framework\Database\ConnectionInterface;
use FlintPHP\Framework\Database\Migration\AdvisoryLockInterface;
use FlintPHP\Framework\Database\Migration\MigrationInterface;
use FlintPHP\Framework\Database\Migration\MigrationLockException;
use FlintPHP\Framework\Database\Migration\Migrator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MigratorAdvisoryLockTest extends TestCase
{
    #[Test]
    public function migrate_acquires_and_releases_lock(): void
    {
        $db = ConnectionFactory::make(['driver' => 'sqlite', 'database' => ':memory:']);

        $events = [];
        $lock = new class ($events) implements AdvisoryLockInterface {
            /** @param list<string> $events */
            public function __construct(private array &$events) {}

            public function acquire(string $name, int $timeoutSeconds): void
            {
                $this->events[] = 'acquire:' . $name . ':' . $timeoutSeconds;
            }

            public function release(string $name): void
            {
                $this->events[] = 'release:' . $name;
            }
        };

        $migrator = new Migrator($db, $lock, lockTimeoutSeconds: 15, lockName: 'flintphp_migrations');

        $m = new class implements MigrationInterface {
            public function id(): string
            {
                return '20260101000000_lock_demo';
            }

            public function up(ConnectionInterface $c): void
            {
                $c->execute('CREATE TABLE lock_demo (id INTEGER PRIMARY KEY)');
            }

            public function down(ConnectionInterface $c): void
            {
                $c->execute('DROP TABLE lock_demo');
            }
        };

        $this->assertSame(['20260101000000_lock_demo'], $migrator->migrate([$m]));
        $this->assertSame(
            ['acquire:flintphp_migrations:15', 'release:flintphp_migrations'],
            $events
        );
    }

    #[Test]
    public function migrate_propagates_lock_failure_and_skips_work(): void
    {
        $db = ConnectionFactory::make(['driver' => 'sqlite', 'database' => ':memory:']);

        $lock = new class implements AdvisoryLockInterface {
            public function acquire(string $name, int $timeoutSeconds): void
            {
                throw new MigrationLockException('busy');
            }

            public function release(string $name): void
            {
            }
        };

        $migrator = new Migrator($db, $lock);

        $ran = false;
        $m = new class ($ran) implements MigrationInterface {
            public function __construct(private bool &$ran) {}

            public function id(): string
            {
                return '20260101000001_never';
            }

            public function up(ConnectionInterface $c): void
            {
                $this->ran = true;
            }

            public function down(ConnectionInterface $c): void
            {
            }
        };

        try {
            $migrator->migrate([$m]);
            $this->fail('Expected MigrationLockException');
        } catch (MigrationLockException $e) {
            $this->assertSame('busy', $e->getMessage());
        }

        $this->assertFalse($ran);
    }

    #[Test]
    public function use_advisory_lock_false_skips_lock(): void
    {
        $db = ConnectionFactory::make(['driver' => 'sqlite', 'database' => ':memory:']);

        $called = false;
        $lock = new class ($called) implements AdvisoryLockInterface {
            public function __construct(private bool &$called) {}

            public function acquire(string $name, int $timeoutSeconds): void
            {
                $this->called = true;
            }

            public function release(string $name): void
            {
                $this->called = true;
            }
        };

        $migrator = new Migrator($db, $lock, useAdvisoryLock: false);

        $m = new class implements MigrationInterface {
            public function id(): string
            {
                return '20260101000002_nolock';
            }

            public function up(ConnectionInterface $c): void
            {
                $c->execute('CREATE TABLE nolock (id INTEGER PRIMARY KEY)');
            }

            public function down(ConnectionInterface $c): void
            {
                $c->execute('DROP TABLE nolock');
            }
        };

        $migrator->migrate([$m]);
        $this->assertFalse($called);
    }
}
