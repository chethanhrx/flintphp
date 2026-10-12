<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Queue;

/**
 * Job wrapper returned by DatabaseQueue::pop() with attempt metadata.
 */
final class ReservedJob implements JobInterface
{
    public function __construct(
        private readonly string $id,
        private readonly SerializableJobInterface $job,
        private readonly int $attempts,
        private readonly DatabaseQueue $queue,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function attempts(): int
    {
        return $this->attempts;
    }

    public function inner(): SerializableJobInterface
    {
        return $this->job;
    }

    public function handle(): void
    {
        $this->job->handle();
    }

    public function release(int $delaySeconds = 0): void
    {
        $this->queue->release($this->id, $this->job, $this->attempts + 1, $delaySeconds);
    }

    public function fail(string $error): void
    {
        $this->queue->fail($this->id, $this->job, $this->attempts + 1, $error);
    }
}
