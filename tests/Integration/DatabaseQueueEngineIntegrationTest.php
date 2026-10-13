<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Integration;

use FlintPHP\Framework\Database\ConnectionFactory;
use FlintPHP\Framework\Database\ConnectionInterface;
use FlintPHP\Framework\Queue\DatabaseQueue;
use FlintPHP\Framework\Queue\ReservedJob;
use FlintPHP\Framework\Queue\SerializableJobInterface;
use FlintPHP\Framework\Queue\Worker;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class IntegrationQueueJob implements SerializableJobInterface
{
    public function __construct(private readonly string $value) {}

    public function handle(): void
    {
        // No-op job for persistence and hydration coverage.
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['value' => $this->value];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): static
    {
        return new self((string) ($data['value'] ?? ''));
    }
}

final class IntegrationFailingQueueJob implements SerializableJobInterface
{
    public function handle(): void
    {
        throw new RuntimeException('integration queue failure');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['kind' => 'failure'];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): static
    {
        return new self();
    }
}

final class DatabaseQueueEngineIntegrationTest extends TestCase
{
    private ConnectionInterface $db;
    private DatabaseQueue $queue;

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

        $this->assertSame(1, (int) $this->db->fetchColumn('SELECT 1'));

        // These tests require a dedicated disposable database.
        $this->db->execute('DROP TABLE IF EXISTS flint_jobs');
        $this->db->execute('DROP TABLE IF EXISTS flint_failed_jobs');

        $this->queue = new DatabaseQueue($this->db, 'integration');
    }

    #[Test]
    public function persists_and_hydrates_a_job_across_database_engines(): void
    {
        $id = $this->queue->push(new IntegrationQueueJob('cross-engine-payload'));

        self::assertSame(1, $this->queue->size());

        $reserved = $this->queue->pop();

        self::assertInstanceOf(ReservedJob::class, $reserved);
        self::assertSame($id, $reserved->id());
        self::assertSame(
            ['value' => 'cross-engine-payload'],
            $reserved->inner()->toArray()
        );
        self::assertSame(0, $reserved->attempts());
        self::assertSame(0, $this->queue->size());
    }

    #[Test]
    public function retries_a_job_then_records_it_as_failed(): void
    {
        $this->queue->push(new IntegrationFailingQueueJob());
        $worker = new Worker(maxAttempts: 2, retryDelaySeconds: 0);

        self::assertTrue($worker->runOnce($this->queue));
        self::assertSame(1, $this->queue->size());

        self::assertTrue($worker->runOnce($this->queue));
        self::assertSame(0, $this->queue->size());
        self::assertSame(
            1,
            (int) $this->db->fetchColumn('SELECT COUNT(*) FROM flint_failed_jobs')
        );

        $failed = $this->db->fetch(
            'SELECT attempts, error FROM flint_failed_jobs WHERE queue = ?',
            ['integration']
        );

        self::assertNotNull($failed);
        self::assertSame(2, (int) $failed['attempts']);
        self::assertSame('integration queue failure', $failed['error']);
    }
}
