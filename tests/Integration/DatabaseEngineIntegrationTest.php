<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Integration;

use FlintPHP\Framework\Database\ConnectionFactory;
use FlintPHP\Framework\Database\ConnectionInterface;
use FlintPHP\Framework\Database\Migration\MigrationInterface;
use FlintPHP\Framework\Database\Migration\Migrator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DatabaseEngineIntegrationTest extends TestCase
{
    private ConnectionInterface $db;

    protected function setUp(): void
    {
        $driver = getenv('FLINTPHP_TEST_DB_DRIVER');

        if ($driver === false || $driver === '') {
            self::markTestSkipped(
                'Set FLINTPHP_TEST_DB_DRIVER to mysql or pgsql to run database integration tests.'
            );
        }

        if (!in_array($driver, ['mysql', 'pgsql'], true)) {
            self::fail('FLINTPHP_TEST_DB_DRIVER must be mysql or pgsql.');
        }

        $this->db = ConnectionFactory::make([
            'driver' => $driver,
            'host' => getenv('FLINTPHP_TEST_DB_HOST') ?: '127.0.0.1',
            'port' => getenv('FLINTPHP_TEST_DB_PORT')
                ?: ($driver === 'mysql' ? 3307 : 5433),
            'database' => getenv('FLINTPHP_TEST_DB_NAME') ?: 'flintphp_test',
            'username' => getenv('FLINTPHP_TEST_DB_USER') ?: 'flintphp',
            'password' => getenv('FLINTPHP_TEST_DB_PASSWORD') ?: 'flintphp_test',
        ]);

        // Force a real connection: configured integration tests must not silently skip.
        $this->assertSame(1, (int) $this->db->fetchColumn('SELECT 1'));

        // These tests require a dedicated disposable database.
        $this->db->execute('DROP TABLE IF EXISTS flint_migrations');
        $this->db->execute('DROP TABLE IF EXISTS flintphp_integration_records');
        $this->db->execute(
            'CREATE TABLE flintphp_integration_records (
                id VARCHAR(191) PRIMARY KEY NOT NULL,
                payload TEXT NOT NULL
            )'
        );
    }

    #[Test]
    public function executes_parameterized_queries_and_reads_results(): void
    {
        $this->db->execute(
            'INSERT INTO flintphp_integration_records (id, payload) VALUES (?, ?)',
            ['record-1', "FlintPHP's database integration test"]
        );

        $row = $this->db->fetch(
            'SELECT id, payload FROM flintphp_integration_records WHERE id = ?',
            ['record-1']
        );

        self::assertNotNull($row);
        self::assertSame('record-1', $row['id']);
        self::assertSame("FlintPHP's database integration test", $row['payload']);
        self::assertSame(
            1,
            (int) $this->db->fetchColumn('SELECT COUNT(*) FROM flintphp_integration_records')
        );
    }

    #[Test]
    public function rolls_back_transactional_data_changes(): void
    {
        try {
            $this->db->transaction(function (): void {
                $this->db->execute(
                    'INSERT INTO flintphp_integration_records (id, payload) VALUES (?, ?)',
                    ['rollback-record', 'must not persist']
                );

                throw new \RuntimeException('Force rollback');
            });

            self::fail('Expected transaction callback to throw.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Force rollback', $exception->getMessage());
        }

        self::assertSame(
            0,
            (int) $this->db->fetchColumn(
                'SELECT COUNT(*) FROM flintphp_integration_records WHERE id = ?',
                ['rollback-record']
            )
        );

        self::assertFalse($this->db->inTransaction());
    }

    #[Test]
    public function tracks_migrations_and_rolls_them_back(): void
    {
        $migration = new class implements MigrationInterface {
            public function id(): string
            {
                return '20990101000000_integration_record';
            }

            public function up(ConnectionInterface $connection): void
            {
                $connection->execute(
                    'INSERT INTO flintphp_integration_records (id, payload) VALUES (?, ?)',
                    ['migration-record', 'created']
                );
            }

            public function down(ConnectionInterface $connection): void
            {
                $connection->execute(
                    'DELETE FROM flintphp_integration_records WHERE id = ?',
                    ['migration-record']
                );
            }
        };

        $migrator = new Migrator($this->db);

        self::assertSame([$migration->id()], $migrator->migrate([$migration]));
        self::assertSame([], $migrator->migrate([$migration]));
        self::assertSame(
            1,
            (int) $this->db->fetchColumn(
                'SELECT COUNT(*) FROM flintphp_integration_records WHERE id = ?',
                ['migration-record']
            )
        );

        self::assertSame([$migration->id()], $migrator->rollback([$migration]));
        self::assertSame(
            0,
            (int) $this->db->fetchColumn(
                'SELECT COUNT(*) FROM flintphp_integration_records WHERE id = ?',
                ['migration-record']
            )
        );
    }
}
