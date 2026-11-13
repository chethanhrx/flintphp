<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Queue;

/**
 * A leased database job. A worker must acknowledge successful handling;
 * otherwise the reservation expires and another worker can retry the job.
 */
final class ReservedJob implements JobInterface
{
    public function __construct(
        private readonly string $id,
        private readonly SerializableJobInterface $job,
        private readonly int $attempts,
        private readonly string $reservationToken,
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

    public function acknowledge(): void
    {
        $this->queue->acknowledge($this->id, $this->reservationToken);
    }

    public function release(int $delaySeconds = 0): void
    {
        $this->queue->release(
            $this->id,
            $this->reservationToken,
            $this->job,
            $this->attempts,
            $delaySeconds,
        );
    }

    public function fail(string $error): void
    {
        $this->queue->fail(
            $this->id,
            $this->reservationToken,
            $this->job,
            $this->attempts,
            $error,
        );
    }
}
