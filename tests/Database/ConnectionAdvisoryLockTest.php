<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Database;

use FlintPHP\Framework\Database\ConnectionFactory;
use FlintPHP\Framework\Database\Migration\ConnectionAdvisoryLock;
use FlintPHP\Framework\Database\Migration\MigrationLockException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConnectionAdvisoryLockTest extends TestCase
{
    #[Test]
    public function sqlite_acquire_and_release_are_noop_success(): void
    {
        $db = ConnectionFactory::make(['driver' => 'sqlite', 'database' => ':memory:']);
        $lock = new ConnectionAdvisoryLock($db);

        $lock->acquire('flintphp_migrations', 1);
        $lock->release('flintphp_migrations');

        $this->assertTrue(true);
    }

    #[Test]
    public function rejects_invalid_lock_name(): void
    {
        $db = ConnectionFactory::make(['driver' => 'sqlite', 'database' => ':memory:']);
        $lock = new ConnectionAdvisoryLock($db);

        $this->expectException(InvalidArgumentException::class);
        $lock->acquire('bad name!', 1);
    }
}
