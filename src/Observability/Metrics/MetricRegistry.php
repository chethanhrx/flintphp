<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Observability\Metrics;

use FlintPHP\Framework\Observability\Contract\MetricInterface;
use FlintPHP\Framework\Observability\Contract\MetricRegistryInterface;
use FlintPHP\Framework\Observability\Exception\ObservabilityException;

/**
 * In-memory metric registry.
 *
 * Each registry instance is fully isolated. No static or global state.
 * Repeated lookups of the same name and type return the same metric instance.
 * Cross-type name collisions are rejected.
 */
final class MetricRegistry implements MetricRegistryInterface
{
    private const NAME_PATTERN = '/\A[a-zA-Z0-9_.\-]{1,128}\z/';

    /** @var array<string, MetricInterface> */
    private array $metrics = [];

    public function counter(string $name): Counter
    {
        return $this->getOrCreate($name, Counter::class);
    }

    public function gauge(string $name): Gauge
    {
        return $this->getOrCreate($name, Gauge::class);
    }

    public function histogram(string $name): Histogram
    {
        return $this->getOrCreate($name, Histogram::class);
    }

    /**
     * Check whether a metric with the given name is registered.
     *
     * @throws ObservabilityException If the name is invalid.
     */
    public function has(string $name): bool
    {
        $this->validateName($name);

        return isset($this->metrics[$name]);
    }

    /**
     * Return a snapshot of all registered metrics.
     *
     * The returned array is a copy. Modifying it does not affect the registry.
     *
     * @return array<string, MetricInterface>
     */
    public function all(): array
    {
        return $this->metrics;
    }

    /**
     * Remove all metrics from this registry.
     *
     * Previously obtained metric instances continue to exist independently
     * but are detached from this registry. Requesting the same name afterward
     * creates a fresh metric with initial state.
     */
    public function clear(): void
    {
        $this->metrics = [];
    }

    /**
     * @template T of MetricInterface
     * @param class-string<T> $class
     * @return T
     *
     * @throws ObservabilityException If the name is invalid or collides with a different type.
     */
    private function getOrCreate(string $name, string $class): MetricInterface
    {
        $this->validateName($name);

        if (isset($this->metrics[$name])) {
            $existing = $this->metrics[$name];

            if (!$existing instanceof $class) {
                throw new ObservabilityException(
                    sprintf(
                        'Metric "%s" is already registered as %s; cannot retrieve as %s.',
                        $name,
                        $existing::class,
                        $class,
                    )
                );
            }

            return $existing;
        }

        $metric = new $class($name);
        $this->metrics[$name] = $metric;

        return $metric;
    }

    /**
     * @throws ObservabilityException If the name is invalid.
     */
    private function validateName(string $name): void
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw new ObservabilityException(
                sprintf('Invalid metric name "%s". Name must match pattern %s.', $name, self::NAME_PATTERN)
            );
        }
    }
}
