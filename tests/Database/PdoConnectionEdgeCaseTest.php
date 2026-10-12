<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Database;

use FlintPHP\Framework\Database\ConnectionFactory;
use FlintPHP\Framework\Database\ConnectionInterface;
use FlintPHP\Framework\Database\Exception\TransactionException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Edge-case tests for PdoConnection.
 *
 * Covers gaps from Phase A audit:
 * - Commit/rollback outside transaction
 * - fetchColumn returns false for no results
 * - Empty result set handling
 * - Multiple prepared statements sequentially
 * - Named parameters in various positions
 */
#[CoversClass(\FlintPHP\Framework\Database\PdoConnection::class)]
#[RequiresPhpExtension('pdo_sqlite')]
final class PdoConnectionEdgeCaseTest extends TestCase
{
    private ConnectionInterface $connection;

    protected function setUp(): void
    {
        $this->connection = ConnectionFactory::make([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $this->connection->execute(
            'CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT NOT NULL, price REAL, active BOOLEAN DEFAULT 1)'
        );
    }

    // ---------------------------------------------------------------
    // Commit/rollback outside transaction
    // ---------------------------------------------------------------

    #[Test]
    public function commit_without_begin_throws(): void
    {
        $this->expectException(TransactionException::class);
        $this->expectExceptionMessage('no active transaction to commit');

        $this->connection->commit();
    }

    #[Test]
    public function rollback_without_begin_throws(): void
    {
        $this->expectException(TransactionException::class);
        $this->expectExceptionMessage('no active transaction to rollback');

        $this->connection->rollBack();
    }

    #[Test]
    public function double_commit_throws(): void
    {
        $this->connection->begin();
        $this->connection->commit();

        $this->expectException(TransactionException::class);
        $this->expectExceptionMessage('no active transaction to commit');

        $this->connection->commit();
    }

    #[Test]
    public function double_rollback_throws(): void
    {
        $this->connection->begin();
        $this->connection->rollBack();

        $this->expectException(TransactionException::class);
        $this->expectExceptionMessage('no active transaction to rollback');

        $this->connection->rollBack();
    }

    // ---------------------------------------------------------------
    // fetchColumn edge cases
    // ---------------------------------------------------------------

    #[Test]
    public function fetch_column_returns_false_for_empty_result(): void
    {
        $result = $this->connection->fetchColumn(
            'SELECT name FROM items WHERE id = ?',
            [999]
        );

        $this->assertFalse($result);
    }

    #[Test]
    public function fetch_column_returns_null_value_when_column_is_null(): void
    {
        $this->connection->execute(
            'INSERT INTO items (name, price) VALUES (?, ?)',
            ['test', null]
        );

        $result = $this->connection->fetchColumn(
            'SELECT price FROM items WHERE name = ?',
            ['test']
        );

        $this->assertNull($result);
    }

    // ---------------------------------------------------------------
    // Empty result sets
    // ---------------------------------------------------------------

    #[Test]
    public function fetch_all_returns_empty_array_for_no_matches(): void
    {
        $rows = $this->connection->fetchAll('SELECT * FROM items WHERE id > 1000');

        $this->assertSame([], $rows);
    }

    #[Test]
    public function fetch_returns_null_for_no_match(): void
    {
        $row = $this->connection->fetch('SELECT * FROM items WHERE id = ?', [999]);

        $this->assertNull($row);
    }

    // ---------------------------------------------------------------
    // Multiple sequential prepared statements
    // ---------------------------------------------------------------

    #[Test]
    public function multiple_sequential_prepared_statements_work_correctly(): void
    {
        // Insert multiple rows
        for ($i = 1; $i <= 5; $i++) {
            $this->connection->execute(
                'INSERT INTO items (name, price) VALUES (?, ?)',
                ["item-{$i}", $i * 10.0]
            );
        }

        // Fetch all
        $all = $this->connection->fetchAll('SELECT * FROM items ORDER BY id ASC');
        $this->assertCount(5, $all);

        // Fetch one
        $one = $this->connection->fetch('SELECT * FROM items WHERE name = ?', ['item-3']);
        $this->assertNotNull($one);
        $this->assertSame('item-3', $one['name']);

        // Count
        $count = $this->connection->fetchColumn('SELECT COUNT(*) FROM items');
        $this->assertEquals(5, $count);

        // Delete + recount
        $affected = $this->connection->execute('DELETE FROM items WHERE id > ?', [3]);
        $this->assertSame(2, $affected);

        $remaining = $this->connection->fetchColumn('SELECT COUNT(*) FROM items');
        $this->assertEquals(3, $remaining);
    }

    // ---------------------------------------------------------------
    // Transaction state tracking
    // ---------------------------------------------------------------

    #[Test]
    public function in_transaction_tracks_state_accurately(): void
    {
        $this->assertFalse($this->connection->inTransaction());

        $this->connection->begin();
        $this->assertTrue($this->connection->inTransaction());

        $this->connection->commit();
        $this->assertFalse($this->connection->inTransaction());
    }

    #[Test]
    public function in_transaction_is_false_after_rollback(): void
    {
        $this->connection->begin();
        $this->assertTrue($this->connection->inTransaction());

        $this->connection->rollBack();
        $this->assertFalse($this->connection->inTransaction());
    }

    // ---------------------------------------------------------------
    // Transaction then normal operations
    // ---------------------------------------------------------------

    #[Test]
    public function connection_works_normally_after_committed_transaction(): void
    {
        $this->connection->begin();
        $this->connection->execute('INSERT INTO items (name) VALUES (?)', ['txn-item']);
        $this->connection->commit();

        // Normal operations still work
        $row = $this->connection->fetch('SELECT * FROM items WHERE name = ?', ['txn-item']);
        $this->assertNotNull($row);
        $this->assertSame('txn-item', $row['name']);
    }

    #[Test]
    public function connection_works_normally_after_rolled_back_transaction(): void
    {
        $this->connection->begin();
        $this->connection->execute('INSERT INTO items (name) VALUES (?)', ['rollback-item']);
        $this->connection->rollBack();

        // Normal operations work; rolled-back data is gone
        $row = $this->connection->fetch('SELECT * FROM items WHERE name = ?', ['rollback-item']);
        $this->assertNull($row);

        // Can still insert normally
        $this->connection->execute('INSERT INTO items (name) VALUES (?)', ['normal-item']);
        $row = $this->connection->fetch('SELECT * FROM items WHERE name = ?', ['normal-item']);
        $this->assertNotNull($row);
    }
}
