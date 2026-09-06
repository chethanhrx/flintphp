<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Observability\Metrics;

use FlintPHP\Framework\Observability\Contract\MetricInterface;
use FlintPHP\Framework\Observability\Exception\ObservabilityException;

/**
 * A signed integer gauge that can be set, incremented, and decremented.
 *
 * Integer overflow and underflow are explicitly detected and rejected.
 */
final class Gauge implements MetricInterface
{
    private const NAME_PATTERN = '/\A[a-zA-Z0-9_.\-]{1,128}\z/';

    private int $value = 0;

    private readonly string $name;

    /**
     * @throws ObservabilityException If the metric name is invalid.
     */
    public function __construct(string $name)
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw new ObservabilityException(
                sprintf('Invalid metric name "%s". Name must match pattern %s.', $name, self::NAME_PATTERN)
            );
        }

        $this->name = $name;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function value(): int
    {
        return $this->value;
    }

    /**
     * Set the gauge to an exact value.
     */
    public function set(int $value): void
    {
        $this->value = $value;
    }

    /**
     * Increment the gauge by a non-negative amount.
     *
     * @throws ObservabilityException If amount is negative or would overflow PHP_INT_MAX.
     */
    public function increment(int $amount = 1): void
    {
        if ($amount < 0) {
            throw new ObservabilityException('Gauge increment amount must be non-negative.');
        }

        if ($amount > \PHP_INT_MAX - $this->value) {
            throw new ObservabilityException(
                sprintf('Gauge increment would overflow PHP_INT_MAX (%d).', \PHP_INT_MAX)
            );
        }

        $this->value += $amount;
    }

    /**
     * Decrement the gauge by a non-negative amount.
     *
     * @throws ObservabilityException If amount is negative or would underflow PHP_INT_MIN.
     */
    public function decrement(int $amount = 1): void
    {
        if ($amount < 0) {
            throw new ObservabilityException('Gauge decrement amount must be non-negative.');
        }

        if ($this->value - \PHP_INT_MIN < $amount) {
            throw new ObservabilityException(
                sprintf('Gauge decrement would underflow PHP_INT_MIN (%d).', \PHP_INT_MIN)
            );
        }

        $this->value -= $amount;
    }
}
