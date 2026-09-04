<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Queue;

/**
 * An in-memory, first-in-first-out (FIFO) queue.
 *
 * This implementation is volatile and does not persist jobs across requests.
 * It does not use object serialization.
 */
final class ArrayQueue implements QueueInterface
{
    /**
     * @var array<string, JobInterface>
     */
    private array $jobs = [];

    public function push(JobInterface $job): string
    {
        // bin2hex(random_bytes(16)) provides a 32-character secure unique identifier,
        // avoiding collisions and avoiding UUID library dependencies.
        $id = bin2hex(random_bytes(16));
        
        $this->jobs[$id] = $job;
        
        return $id;
    }

    public function pop(): ?JobInterface
    {
        if (empty($this->jobs)) {
            return null;
        }

        $firstKey = array_key_first($this->jobs);
        
        $job = $this->jobs[$firstKey];
        unset($this->jobs[$firstKey]);
        
        return $job;
    }

    public function size(): int
    {
        return count($this->jobs);
    }

    public function clear(): void
    {
        $this->jobs = [];
    }
}
