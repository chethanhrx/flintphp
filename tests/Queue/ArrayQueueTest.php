<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Queue;

use FlintPHP\Framework\Queue\ArrayQueue;
use FlintPHP\Framework\Queue\JobInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArrayQueue::class)]
final class ArrayQueueTest extends TestCase
{
    private ArrayQueue $queue;

    protected function setUp(): void
    {
        $this->queue = new ArrayQueue();
    }

    #[Test]
    public function it_starts_empty(): void
    {
        $this->assertSame(0, $this->queue->size());
        $this->assertNull($this->queue->pop());
    }

    #[Test]
    public function it_can_push_and_pop_a_job(): void
    {
        $job = $this->createMock(JobInterface::class);
        
        $id = $this->queue->push($job);
        
        $this->assertIsString($id);
        $this->assertSame(32, strlen($id));
        $this->assertSame(1, $this->queue->size());
        
        $popped = $this->queue->pop();
        
        $this->assertSame($job, $popped);
        $this->assertSame(0, $this->queue->size());
    }

    #[Test]
    public function it_processes_jobs_in_fifo_order(): void
    {
        $job1 = $this->createMock(JobInterface::class);
        $job2 = $this->createMock(JobInterface::class);
        $job3 = $this->createMock(JobInterface::class);

        $id1 = $this->queue->push($job1);
        $id2 = $this->queue->push($job2);
        $id3 = $this->queue->push($job3);

        $this->assertNotSame($id1, $id2);
        $this->assertNotSame($id2, $id3);
        $this->assertNotSame($id1, $id3);

        $this->assertSame(3, $this->queue->size());

        $this->assertSame($job1, $this->queue->pop());
        $this->assertSame(2, $this->queue->size());

        $this->assertSame($job2, $this->queue->pop());
        $this->assertSame(1, $this->queue->size());

        $this->assertSame($job3, $this->queue->pop());
        $this->assertSame(0, $this->queue->size());
    }

    #[Test]
    public function it_can_clear_all_jobs(): void
    {
        $job1 = $this->createMock(JobInterface::class);
        $job2 = $this->createMock(JobInterface::class);

        $this->queue->push($job1);
        $this->queue->push($job2);

        $this->assertSame(2, $this->queue->size());

        $this->queue->clear();

        $this->assertSame(0, $this->queue->size());
        $this->assertNull($this->queue->pop());
    }

    #[Test]
    public function clearing_an_empty_queue_is_safe(): void
    {
        $this->queue->clear();
        $this->assertSame(0, $this->queue->size());
    }

    #[Test]
    public function multiple_queue_instances_are_isolated(): void
    {
        $queue2 = new ArrayQueue();
        
        $job = $this->createMock(JobInterface::class);
        $this->queue->push($job);
        
        $this->assertSame(1, $this->queue->size());
        $this->assertSame(0, $queue2->size());
    }
}
