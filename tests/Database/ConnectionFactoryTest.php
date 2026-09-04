<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Database;

use FlintPHP\Framework\Database\ConnectionFactory;
use FlintPHP\Framework\Database\Exception\ConnectionException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConnectionFactory::class)]
final class ConnectionFactoryTest extends TestCase
{
    #[Test]
    #[RequiresPhpExtension('pdo_sqlite')]
    public function it_creates_sqlite_memory_connection(): void
    {
        $connection = ConnectionFactory::make([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $this->assertInstanceOf(\PDO::class, $connection->pdo());
    }

    #[Test]
    public function it_throws_if_driver_is_missing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A driver must be specified.');

        ConnectionFactory::make([]);
    }

    #[Test]
    public function it_throws_on_unsupported_driver(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported database driver: foo');

        ConnectionFactory::make(['driver' => 'foo']);
    }

    #[Test]
    public function it_does_not_leak_passwords_on_connection_failure(): void
    {
        $connection = ConnectionFactory::make([
            'driver' => 'mysql',
            'host' => '255.255.255.255', // Invalid host to force timeout/refusal
            'database' => 'flint',
            'username' => 'root',
            'password' => 'super_secret_password_123',
            'options' => [
                \PDO::ATTR_TIMEOUT => 1,
            ],
        ]);

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Could not connect to the database.');

        try {
            $connection->pdo();
        } catch (ConnectionException $e) {
            $this->assertStringNotContainsString('super_secret_password_123', $e->getMessage());
            $this->assertStringNotContainsString('super_secret_password_123', (string) $e);
            throw $e;
        }
    }
}
