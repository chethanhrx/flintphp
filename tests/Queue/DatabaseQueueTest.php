<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Queue;

use FlintPHP\Framework\Database\ConnectionFactory;
use FlintPHP\Framework\Queue\DatabaseQueue;
use FlintPHP\Framework\Queue\ReservedJob;
use FlintPHP\Framework\Queue\SerializableJobInterface;
use FlintPHP\Framework\Queue\Worker;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ExampleJob implements SerializableJobInterface
{
    public static int $handled = 0;
    /** @var list<string> */
    public static array $handledNames = [];

    public function __construct(private readonly string $name) {}

    public function handle(): void
    {
        self::$handled++;
        self::$handledNames[] = $this->name;
    }

    public function toArray(): array
    {
        return ['name' => $this->name];
    }

    public static function fromArray(array $data): static
    {
        return new self((string) $data['name']);
    }
}

final class FailingJob implements SerializableJobInterface
{
    public function handle(): void
    {
        throw new RuntimeException('boom');
    }

    public function toArray(): array
    {
        return [];
    }

    public static function fromArray(array $data): static
    {
        return new self();
    }
}

final class DatabaseQueueTest extends TestCase
{
    #[Test]
    public function push_pop_and_handle_acknowledges_successful_job(): void
    {
        ExampleJob::$handled = 0;
        ExampleJob::$handledNames = [];
        $db = ConnectionFactory::make(['driver' => 'sqlite', 'database' => ':memory:']);
        $queue = new DatabaseQueue($db);
        $queue->push(new ExampleJob('a'));

        self::assertSame(1, $queue->size());
        $worker = new Worker(maxAttempts: 3);
        self::assertTrue($worker->runOnce($queue));
        self::assertSame(1, ExampleJob::$handled);
        self::assertSame(0, $queue->size());
    }

    #[Test]
    public function database_queue_preserves_fifo_order_for_sequential_pushes(): void
    {
        ExampleJob::$handledNames = [];
        $db = ConnectionFactory::make(['driver' => 'sqlite', 'database' => ':memory:']);
        $queue = new DatabaseQueue($db);

        $queue->push(new ExampleJob('first'));
        usleep(2_000);
        $queue->push(new ExampleJob('second'));
        usleep(2_000);
        $queue->push(new ExampleJob('third'));

        $worker = new Worker();
        self::assertSame(3, $worker->run($queue));
        self::assertSame(['first', 'second', 'third'], ExampleJob::$handledNames);
    }

    #[Test]
    public function failed_jobs_retry_then_dead_letter(): void
    {
        $db = ConnectionFactory::make(['driver' => 'sqlite', 'database' => ':memory:']);
        $queue = new DatabaseQueue($db);
        $queue->push(new FailingJob());
        $worker = new Worker(maxAttempts: 2, retryDelaySeconds: 0);

        $worker->runOnce($queue);
        self::assertSame(1, $queue->size());

        $worker->runOnce($queue);
        self::assertSame(0, $queue->size());

        $failed = $db->fetch('SELECT attempts FROM flint_failed_jobs');
        self::assertNotNull($failed);
        self::assertSame(2, (int) $failed['attempts']);
    }

    #[Test]
    public function upgrades_legacy_queue_tables_with_reservation_columns(): void
    {
        $db = ConnectionFactory::make(['driver' => 'sqlite', 'database' => ':memory:']);
        $db->execute(
            'CREATE TABLE flint_jobs (
                id VARCHAR(191) PRIMARY KEY NOT NULL,
                queue VARCHAR(64) NOT NULL,
                job_class TEXT NOT NULL,
                payload TEXT NOT NULL,
                attempts INTEGER NOT NULL DEFAULT 0,
                available_at VARCHAR(32) NOT NULL,
                created_at VARCHAR(32) NOT NULL
            )'
        );
        $db->execute(
            'CREATE TABLE flint_failed_jobs (
                id VARCHAR(191) PRIMARY KEY NOT NULL,
                queue VARCHAR(64) NOT NULL,
                job_class TEXT NOT NULL,
                payload TEXT NOT NULL,
                attempts INTEGER NOT NULL,
                error TEXT NOT NULL,
                failed_at VARCHAR(32) NOT NULL
            )'
        );

        $queue = new DatabaseQueue($db);
        $queue->push(new ExampleJob('after-upgrade'));
        $reserved = $queue->pop();

        self::assertInstanceOf(ReservedJob::class, $reserved);
        self::assertSame(1, $reserved->attempts());
        $reserved->acknowledge();
        self::assertSame(0, $queue->size());
    }

    #[Test]
    public function reservation_prevents_double_claim_across_connections_and_recovers_abandoned_jobs(): void
    {
        $database = tempnam(sys_get_temp_dir(), 'flint-queue-');
        self::assertNotFalse($database);

        try {
            $firstDb = ConnectionFactory::make(['driver' => 'sqlite', 'database' => $database]);
            $secondDb = ConnectionFactory::make(['driver' => 'sqlite', 'database' => $database]);
            $firstQueue = new DatabaseQueue($firstDb, reservationSeconds: 1);
            $secondQueue = new DatabaseQueue($secondDb, reservationSeconds: 1);
            $firstQueue->push(new ExampleJob('recoverable'));

            $firstWorker = $firstQueue->pop();
            self::assertInstanceOf(ReservedJob::class, $firstWorker);
            self::assertNull($secondQueue->pop());
            self::assertSame(1, $secondQueue->size());

            // Simulate the first worker process dying without ack/release/fail.
            sleep(2);
            $secondWorker = $secondQueue->pop();

            self::assertInstanceOf(ReservedJob::class, $secondWorker);
            self::assertSame($firstWorker->id(), $secondWorker->id());
            $secondWorker->acknowledge();
            self::assertSame(0, $firstQueue->size());
        } finally {
            @unlink($database);
        }
    }

    #[Test]
    public function stale_reservation_cannot_acknowledge_or_release_reclaimed_job(): void
    {
        $db = ConnectionFactory::make(['driver' => 'sqlite', 'database' => ':memory:']);
        $queue = new DatabaseQueue($db, reservationSeconds: 1);
        $queue->push(new ExampleJob('lease'));

        $stale = $queue->pop();
        self::assertInstanceOf(ReservedJob::class, $stale);
        sleep(2);
        $current = $queue->pop();
        self::assertInstanceOf(ReservedJob::class, $current);

        $this->expectException(RuntimeException::class);
        $stale->acknowledge();
    }
}
