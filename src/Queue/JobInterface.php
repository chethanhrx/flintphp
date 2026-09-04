<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Queue;

/**
 * Contract for jobs processed by the queue system.
 */
interface JobInterface
{
    /**
     * Execute the job logic.
     */
    public function handle(): void;
}
