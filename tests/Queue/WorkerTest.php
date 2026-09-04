<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Queue;

use FlintPHP\Framework\Queue\ArrayQueue;
use FlintPHP\Framework\Queue\JobInterface;
use FlintPHP\Framework\Queue\Worker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Worker::class)]
final class WorkerTest extends TestCase
{
    private Worker $worker;
    private ArrayQueue $queue;

    protected function setUp(): void
    {
        $this->worker = new Worker();
        $this->queue = new ArrayQueue();
    }

    #[Test]
    public function it_returns_false_when_queue_is_empty(): void
    {
        $this->assertFalse($this->worker->runOnce($this->queue));
    }

    #[Test]
    public function it_processes_a_job_and_returns_true(): void
    {
        $job = $this->createMock(JobInterface::class);
        $job->expects($this->once())->method('handle');

        $this->queue->push($job);

        $this->assertTrue($this->worker->runOnce($this->queue));
        
        // Ensure the job was popped
        $this->assertSame(0, $this->queue->size());
    }

    #[Test]
    public function it_processes_exactly_one_job_per_call(): void
    {
        $job1 = $this->createMock(JobInterface::class);
        $job1->expects($this->once())->method('handle');

        $job2 = $this->createMock(JobInterface::class);
        $job2->expects($this->never())->method('handle');

        $this->queue->push($job1);
        $this->queue->push($job2);

        $this->assertTrue($this->worker->runOnce($this->queue));
        
        $this->assertSame(1, $this->queue->size());
    }

    #[Test]
    public function it_allows_exceptions_to_propagate_and_leaves_job_removed(): void
    {
        $job = $this->createMock(JobInterface::class);
        $job->expects($this->once())
            ->method('handle')
            ->willThrowException(new RuntimeException('Job failed'));

        $this->queue->push($job);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Job failed');

        try {
            $this->worker->runOnce($this->queue);
        } finally {
            // Assert that the failed job is not requeued
            $this->assertSame(0, $this->queue->size());
        }
    }
}
