<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Queue;

use FlintPHP\Framework\Database\ConnectionFactory;
use FlintPHP\Framework\Queue\DatabaseQueue;
use FlintPHP\Framework\Queue\SerializableJobInterface;
use FlintPHP\Framework\Queue\Worker;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ExampleJob implements SerializableJobInterface
{
    public static int $handled = 0;

    public function __construct(private readonly string $name) {}

    public function handle(): void
    {
        self::$handled++;
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
    public function push_pop_and_handle(): void
    {
        ExampleJob::$handled = 0;
        $db = ConnectionFactory::make(['driver' => 'sqlite', 'database' => ':memory:']);
        $queue = new DatabaseQueue($db);

        $queue->push(new ExampleJob('a'));
        $this->assertSame(1, $queue->size());

        $worker = new Worker(maxAttempts: 3);
        $this->assertTrue($worker->runOnce($queue));
        $this->assertSame(1, ExampleJob::$handled);
        $this->assertSame(0, $queue->size());
    }

    #[Test]
    public function failed_jobs_retry_then_dead_letter(): void
    {
        $db = ConnectionFactory::make(['driver' => 'sqlite', 'database' => ':memory:']);
        $queue = new DatabaseQueue($db);
        $queue->push(new FailingJob());

        $worker = new Worker(maxAttempts: 2, retryDelaySeconds: 0);

        $worker->runOnce($queue); // attempt 0 → release as 1
        $this->assertSame(1, $queue->size());

        $worker->runOnce($queue); // attempt 1 → fail (max 2)
        $this->assertSame(0, $queue->size());

        $failed = $db->fetchColumn('SELECT COUNT(*) FROM flint_failed_jobs');
        $this->assertSame(1, (int) $failed);
    }

    #[Test]
    public function concurrent_workers_do_not_duplicate_job(): void
    {
        $db = ConnectionFactory::make(['driver' => 'sqlite', 'database' => ':memory:']);
        $queue = new DatabaseQueue($db);
        $queue->push(new ExampleJob('unique_job'));

        // First worker claims the job
        $job1 = $queue->pop();
        $this->assertNotNull($job1);

        // Second worker attempts pop on empty queue
        $job2 = $queue->pop();
        $this->assertNull($job2);
    }
}
