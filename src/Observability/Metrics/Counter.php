<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Observability\Metrics;

use FlintPHP\Framework\Observability\Contract\MetricInterface;
use FlintPHP\Framework\Observability\Exception\ObservabilityException;

/**
 * A monotonically increasing integer counter.
 *
 * The counter starts at zero and can only be incremented by non-negative amounts.
 * Integer overflow is explicitly detected and rejected.
 */
final class Counter implements MetricInterface
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
     * Increment the counter by the given non-negative amount.
     *
     * @throws ObservabilityException If amount is negative or would overflow PHP_INT_MAX.
     */
    public function increment(int $amount = 1): void
    {
        if ($amount < 0) {
            throw new ObservabilityException('Counter increment amount must be non-negative.');
        }

        if ($amount > \PHP_INT_MAX - $this->value) {
            throw new ObservabilityException(
                sprintf('Counter increment would overflow PHP_INT_MAX (%d).', \PHP_INT_MAX)
            );
        }

        $this->value += $amount;
    }
}
