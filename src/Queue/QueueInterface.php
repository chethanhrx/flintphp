<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Queue;

/**
 * Contract for a first-in-first-out (FIFO) queue.
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
     * Remove and return the next job from the front of the queue.
     *
     * @return JobInterface|null The next job, or null if the queue is empty.
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
