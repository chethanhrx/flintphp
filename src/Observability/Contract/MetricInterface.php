<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Observability\Contract;

/**
 * Common contract for all metric types.
 */
interface MetricInterface
{
    /**
     * Return the metric's validated name.
     */
    public function name(): string;
}
