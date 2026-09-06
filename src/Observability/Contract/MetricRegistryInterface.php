<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Observability\Contract;

use FlintPHP\Framework\Observability\Metrics\Counter;
use FlintPHP\Framework\Observability\Metrics\Gauge;
use FlintPHP\Framework\Observability\Metrics\Histogram;

/**
 * Contract for an in-memory metric registry.
 */
interface MetricRegistryInterface
{
    public function counter(string $name): Counter;

    public function gauge(string $name): Gauge;

    public function histogram(string $name): Histogram;

    public function has(string $name): bool;

    /**
     * Return a snapshot of all registered metrics.
     *
     * @return array<string, MetricInterface>
     */
    public function all(): array;

    public function clear(): void;
}
