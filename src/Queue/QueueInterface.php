<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Queue;

/**
 * Contract for a queue that returns the oldest available job first.
 */
interface QueueInterface
{
    /**
     * Push a new job onto the end of the queue.
     *
     * @param JobInterface $job The job to queue.
     * @return string A unique identifier for the queued job.
     */
    public function push(JobInterface $job): string;

    /**
     * Take the next available job from the queue.
     *
     * Volatile implementations may remove it immediately. Durable
     * implementations may return a reservation that must be acknowledged
     * after successful handling.
     *
     * @return JobInterface|null The next available job, or null if the queue is empty.
     */
    public function pop(): ?JobInterface;

    /**
     * Get the number of jobs currently in the queue.
     *
     * @return int The queue size.
     */
    public function size(): int;

    /**
     * Remove all jobs from the queue.
     */
    public function clear(): void;
}
