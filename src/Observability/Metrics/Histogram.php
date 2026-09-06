<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Observability\Metrics;

use FlintPHP\Framework\Observability\Contract\MetricInterface;
use FlintPHP\Framework\Observability\Exception\ObservabilityException;

/**
 * A histogram that tracks observed floating-point values.
 *
 * Maintains count, sum, minimum, and maximum.
 *
 * Note: The sum uses standard IEEE-754 double-precision arithmetic. For very
 * large observation counts or extreme value ranges, floating-point precision
 * loss may occur. This is inherent to hardware floating-point and is not
 * mitigated by arbitrary-precision arithmetic in v0.21.
 */
final class Histogram implements MetricInterface
{
    private const NAME_PATTERN = '/\A[a-zA-Z0-9_.\-]{1,128}\z/';

    private int $count = 0;
    private float $sum = 0.0;
    private ?float $minimum = null;
    private ?float $maximum = null;

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

    public function count(): int
    {
        return $this->count;
    }

    public function sum(): float
    {
        return $this->sum;
    }

    public function minimum(): ?float
    {
        return $this->minimum;
    }

    public function maximum(): ?float
    {
        return $this->maximum;
    }

    /**
     * Record a finite observation.
     *
     * @throws ObservabilityException If the value is NaN, +INF, or -INF.
     * @throws ObservabilityException If the count would overflow PHP_INT_MAX.
     * @throws ObservabilityException If the resulting sum becomes non-finite.
     */
    public function observe(float $value): void
    {
        if (!is_finite($value)) {
            throw new ObservabilityException('Histogram observation must be finite (no NaN, +INF, or -INF).');
        }

        if ($this->count === \PHP_INT_MAX) {
            throw new ObservabilityException(
                sprintf('Histogram observation count would overflow PHP_INT_MAX (%d).', \PHP_INT_MAX)
            );
        }

        $newSum = $this->sum + $value;

        if (!is_finite($newSum)) {
            throw new ObservabilityException('Histogram sum became non-finite after observation.');
        }

        $this->count++;
        $this->sum = $newSum;

        if ($this->minimum === null || $value < $this->minimum) {
            $this->minimum = $value;
        }

        if ($this->maximum === null || $value > $this->maximum) {
            $this->maximum = $value;
        }
    }
}
