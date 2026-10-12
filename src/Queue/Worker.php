<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Queue;

use Throwable;

/**
 * Processes jobs from a queue.
 *
 * For DatabaseQueue + ReservedJob: failed jobs are released (retry) or failed
 * (dead-letter) based on maxAttempts. ArrayQueue behaviour is unchanged:
 * exceptions propagate and the job is already removed.
 */
final class Worker
{
    public function __construct(
        private readonly int $maxAttempts = 3,
        private readonly int $retryDelaySeconds = 10,
    ) {
        if ($this->maxAttempts < 1) {
            throw new \InvalidArgumentException('maxAttempts must be >= 1');
        }
    }

    /**
     * Process one job. Returns true if a job was taken from the queue.
     */
    public function runOnce(QueueInterface $queue): bool
    {
        $job = $queue->pop();

        if ($job === null) {
            return false;
        }

        try {
            $job->handle();
        } catch (Throwable $e) {
            if ($job instanceof ReservedJob) {
                if ($job->attempts() + 1 < $this->maxAttempts) {
                    $job->release($this->retryDelaySeconds);
                } else {
                    $job->fail($e->getMessage());
                }

                return true;
            }

            throw $e;
        }

        return true;
    }

    /**
     * Process up to $max jobs. Returns number of jobs processed.
     */
    public function run(QueueInterface $queue, int $max = 100): int
    {
        $processed = 0;

        while ($processed < $max && $this->runOnce($queue)) {
            $processed++;
        }

        return $processed;
    }
}
