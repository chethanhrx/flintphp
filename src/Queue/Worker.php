<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Queue;

/**
 * Processes jobs from a queue.
 */
final class Worker
{
    /**
     * Attempt to process a single job from the given queue.
     *
     * If the queue is empty, returns false without doing anything.
     * If a job is available, calls handle() on the job and returns true.
     *
     * Note: If handle() throws an exception, the exception propagates up naturally.
     * The job is already removed from the queue and will not be retried automatically.
     *
     * @param QueueInterface $queue The queue to process from.
     * @return bool True if a job was processed, false if the queue was empty.
     */
    public function runOnce(QueueInterface $queue): bool
    {
        $job = $queue->pop();

        if ($job === null) {
            return false;
        }

        $job->handle();

        return true;
    }
}
