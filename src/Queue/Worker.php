<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Queue;

use Throwable;

/**
 * Processes jobs from a queue.
 *
 * Database reservations use at-least-once delivery: a successful job is
 * acknowledged, while an unacknowledged reservation becomes visible again
 * after its lease expires. Handlers should therefore be idempotent.
 *
 * ArrayQueue remains an in-memory queue and removes a job before handling it.
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
                if ($job->attempts() < $this->maxAttempts) {
                    $job->release($this->retryDelaySeconds);
                } else {
                    $job->fail($e->getMessage());
                }
                return true;
            }

            throw $e;
        }

        if ($job instanceof ReservedJob) {
            $job->acknowledge();
        }

        return true;
    }

    public function run(QueueInterface $queue, int $max = 100): int
    {
        $processed = 0;
        while ($processed < $max && $this->runOnce($queue)) {
            $processed++;
        }

        return $processed;
    }
}
