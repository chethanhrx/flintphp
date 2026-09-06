<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Database;

use FlintPHP\Framework\Database\ConnectionFactory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConnectionFactory::class)]
final class ConnectionFactoryHardeningTest extends TestCase
{
    #[Test]
    public function non_string_driver_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A driver must be specified.');

        ConnectionFactory::make(['driver' => ['mysql']]);
    }

    #[Test]
    public function non_string_sqlite_database_throws_and_discloses_only_the_type(): void
    {
        try {
            ConnectionFactory::make(['driver' => 'sqlite', 'database' => 123]);
            $this->fail('Malformed database path was accepted.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('must be a string, got int', $e->getMessage());
            // The offending VALUE must never appear in the message.
            $this->assertStringNotContainsString('123', $this->stripTypeToken('got int', $e->getMessage()));
        }
    }

    #[Test]
    public function non_scalar_host_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"host" must be a string or integer, got array');

        ConnectionFactory::make([
            'driver' => 'mysql',
            'host' => ['evil'],
        ]);
    }

    #[Test]
    public function non_numeric_port_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"port" must be an integer or numeric string, got string');

        ConnectionFactory::make([
            'driver' => 'pgsql',
            'port' => 'not-a-port',
        ]);
    }

    #[Test]
    public function numeric_string_port_is_accepted(): void
    {
        $connection = ConnectionFactory::make([
            'driver' => 'sqlite',
            'database' => ':memory:',
            // Irrelevant for sqlite, but proves the validator accepts valid shapes.
            'port' => '5432',
        ]);

        $this->assertInstanceOf(\PDO::class, $connection->pdo());
    }

    #[Test]
    public function non_string_username_throws_without_disclosing_the_value(): void
    {
        try {
            ConnectionFactory::make([
                'driver' => 'mysql',
                'username' => ['array-credential'],
            ]);
            $this->fail('Array username was accepted.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('"username" must be a string or null, got array', $e->getMessage());
            $this->assertStringNotContainsString('array-credential', $e->getMessage());
        }
    }

    #[Test]
    public function non_string_password_throws_without_disclosing_the_value(): void
    {
        try {
            ConnectionFactory::make([
                'driver' => 'mysql',
                'password' => 12345,
            ]);
            $this->fail('Integer password was accepted.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('"password" must be a string or null, got int', $e->getMessage());
            $this->assertStringNotContainsString('12345', $e->getMessage());
        }
    }

    #[Test]
    public function non_array_options_throw(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"options" must be an array, got string');

        ConnectionFactory::make([
            'driver' => 'sqlite',
            'options' => 'PDO::ATTR_ERRMODE',
        ]);
    }

    #[Test]
    public function valid_full_mysql_configuration_is_accepted(): void
    {
        // Do not actually connect: just verify validation passes by inspecting
        // laziness (pdo() would attempt a real connection, so we only assert
        // construction succeeded).
        $connection = ConnectionFactory::make([
            'driver' => 'mysql',
            'host' => 'localhost',
            'port' => 3306,
            'database' => 'app',
            'charset' => 'utf8mb4',
            'username' => 'root',
            'password' => 'secret',
        ]);

        $this->assertInstanceOf(\FlintPHP\Framework\Database\ConnectionInterface::class, $connection);
        $this->assertFalse($connection->inTransaction());
    }

    private function stripTypeToken(string $token, string $message): string
    {
        return str_replace($token, '', $message);
    }
}
