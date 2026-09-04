<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Database;

use FlintPHP\Framework\Database\ConnectionFactory;
use FlintPHP\Framework\Database\ConnectionInterface;
use FlintPHP\Framework\Database\Exception\QueryException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

#[CoversClass(\FlintPHP\Framework\Database\PdoConnection::class)]
#[RequiresPhpExtension('pdo_sqlite')]
final class PdoConnectionTest extends TestCase
{
    private ConnectionInterface $connection;

    protected function setUp(): void
    {
        $this->connection = ConnectionFactory::make([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $this->connection->execute(
            'CREATE TABLE test_users (id INTEGER PRIMARY KEY, name TEXT, age INTEGER, active BOOLEAN)'
        );
    }

    #[Test]
    public function it_can_execute_inserts_and_return_affected_rows(): void
    {
        $affected = $this->connection->execute(
            'INSERT INTO test_users (name, age, active) VALUES (?, ?, ?)',
            ['Chethan', 25, true]
        );

        $this->assertSame(1, $affected);
    }

    #[Test]
    public function it_can_fetch_all_rows(): void
    {
        $this->connection->execute('INSERT INTO test_users (name, age, active) VALUES (?, ?, ?)', ['A', 10, true]);
        $this->connection->execute('INSERT INTO test_users (name, age, active) VALUES (?, ?, ?)', ['B', 20, false]);

        $rows = $this->connection->fetchAll('SELECT * FROM test_users ORDER BY id ASC');

        $this->assertCount(2, $rows);
        $this->assertSame('A', $rows[0]['name']);
        $this->assertSame('B', $rows[1]['name']);
    }

    #[Test]
    public function it_can_fetch_a_single_row(): void
    {
        $this->connection->execute('INSERT INTO test_users (name, age, active) VALUES (?, ?, ?)', ['A', 10, true]);

        $row = $this->connection->fetch('SELECT * FROM test_users WHERE name = :name', ['name' => 'A']);

        $this->assertNotNull($row);
        $this->assertSame('A', $row['name']);

        $missing = $this->connection->fetch('SELECT * FROM test_users WHERE name = ?', ['Missing']);
        $this->assertNull($missing);
    }

    #[Test]
    public function it_can_fetch_a_scalar_column(): void
    {
        $this->connection->execute('INSERT INTO test_users (name, age, active) VALUES (?, ?, ?)', ['A', 10, true]);

        $age = $this->connection->fetchColumn('SELECT age FROM test_users WHERE name = ?', ['A']);

        // SQLite returns '10' (string) or 10 depending on PDO config, but we disabled emulated prepares.
        $this->assertEquals(10, $age);
    }

    #[Test]
    public function it_throws_query_exception_on_invalid_sql(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('SQL: SELECT * FROM nonexistent_table');

        $this->connection->fetchAll('SELECT * FROM nonexistent_table');
    }

    #[Test]
    public function it_properly_binds_various_types(): void
    {
        $this->connection->execute('INSERT INTO test_users (name, age, active) VALUES (:n, :a, :act)', [
            'n' => 'John',
            'a' => null,
            'act' => false,
        ]);

        $user = $this->connection->fetch('SELECT * FROM test_users WHERE name = ?', ['John']);

        $this->assertSame('John', $user['name']);
        $this->assertNull($user['age']);
        
        // SQLite PDO driver returns '0' or 0 for false boolean depending on versions, so we use loose assertion
        $this->assertFalse((bool) $user['active']);
    }

    #[Test]
    public function connection_is_lazy(): void
    {
        $connection = ConnectionFactory::make([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $reflection = new \ReflectionClass($connection);
        $pdoProperty = $reflection->getProperty('pdo');

        $this->assertNull($pdoProperty->getValue($connection));

        $connection->pdo();

        $this->assertNotNull($pdoProperty->getValue($connection));
    }

    #[Test]
    public function it_throws_on_array_parameter(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Array or object parameters are not supported.');

        $this->connection->execute('SELECT * FROM test_users WHERE id IN (?)', [[1, 2, 3]]);
    }

    #[Test]
    public function it_throws_on_object_parameter(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Array or object parameters are not supported.');

        $this->connection->execute('SELECT * FROM test_users WHERE name = ?', [new \stdClass()]);
    }
}
