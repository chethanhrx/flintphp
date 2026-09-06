<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Observability\Metrics;

use FlintPHP\Framework\Observability\Contract\MetricInterface;
use FlintPHP\Framework\Observability\Exception\ObservabilityException;
use FlintPHP\Framework\Observability\Metrics\Histogram;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Histogram::class)]
final class HistogramTest extends TestCase
{
    #[Test]
    public function it_implements_metric_interface(): void
    {
        $this->assertInstanceOf(MetricInterface::class, new Histogram('test'));
    }

    // ── Empty state ──

    #[Test]
    public function it_starts_empty(): void
    {
        $h = new Histogram('test');
        $this->assertSame(0, $h->count());
        $this->assertSame(0.0, $h->sum());
        $this->assertNull($h->minimum());
        $this->assertNull($h->maximum());
    }

    // ── Observation ──

    #[Test]
    public function it_records_positive_observation(): void
    {
        $h = new Histogram('test');
        $h->observe(3.14);

        $this->assertSame(1, $h->count());
        $this->assertSame(3.14, $h->sum());
        $this->assertSame(3.14, $h->minimum());
        $this->assertSame(3.14, $h->maximum());
    }

    #[Test]
    public function it_records_negative_observation(): void
    {
        $h = new Histogram('test');
        $h->observe(-2.5);

        $this->assertSame(1, $h->count());
        $this->assertSame(-2.5, $h->sum());
        $this->assertSame(-2.5, $h->minimum());
        $this->assertSame(-2.5, $h->maximum());
    }

    #[Test]
    public function it_records_zero_observation(): void
    {
        $h = new Histogram('test');
        $h->observe(0.0);

        $this->assertSame(1, $h->count());
        $this->assertSame(0.0, $h->sum());
        $this->assertSame(0.0, $h->minimum());
        $this->assertSame(0.0, $h->maximum());
    }

    #[Test]
    public function it_tracks_multiple_observations(): void
    {
        $h = new Histogram('test');
        $h->observe(10.0);
        $h->observe(20.0);
        $h->observe(5.0);

        $this->assertSame(3, $h->count());
        $this->assertSame(35.0, $h->sum());
        $this->assertSame(5.0, $h->minimum());
        $this->assertSame(20.0, $h->maximum());
    }

    #[Test]
    public function it_updates_minimum_correctly(): void
    {
        $h = new Histogram('test');
        $h->observe(10.0);
        $h->observe(5.0);
        $h->observe(7.0);
        $h->observe(3.0);

        $this->assertSame(3.0, $h->minimum());
    }

    #[Test]
    public function it_updates_maximum_correctly(): void
    {
        $h = new Histogram('test');
        $h->observe(5.0);
        $h->observe(10.0);
        $h->observe(7.0);

        $this->assertSame(10.0, $h->maximum());
    }

    // ── Rejection ──

    #[Test]
    public function it_rejects_nan(): void
    {
        $h = new Histogram('test');
        $this->expectException(ObservabilityException::class);
        $this->expectExceptionMessage('finite');
        $h->observe(\NAN);
    }

    #[Test]
    public function it_rejects_positive_infinity(): void
    {
        $h = new Histogram('test');
        $this->expectException(ObservabilityException::class);
        $this->expectExceptionMessage('finite');
        $h->observe(\INF);
    }

    #[Test]
    public function it_rejects_negative_infinity(): void
    {
        $h = new Histogram('test');
        $this->expectException(ObservabilityException::class);
        $this->expectExceptionMessage('finite');
        $h->observe(-\INF);
    }

    #[Test]
    public function it_preserves_state_after_rejected_observation(): void
    {
        $h = new Histogram('test');
        $h->observe(5.0);

        try {
            $h->observe(\NAN);
        } catch (ObservabilityException) {
        }

        $this->assertSame(1, $h->count());
        $this->assertSame(5.0, $h->sum());
        $this->assertSame(5.0, $h->minimum());
        $this->assertSame(5.0, $h->maximum());
    }

    #[Test]
    public function it_rejects_sum_overflow_to_infinity(): void
    {
        $h = new Histogram('test');
        $h->observe(\PHP_FLOAT_MAX);

        $this->expectException(ObservabilityException::class);
        $this->expectExceptionMessage('non-finite');
        $h->observe(\PHP_FLOAT_MAX);
    }

    #[Test]
    public function it_preserves_state_after_sum_overflow(): void
    {
        $h = new Histogram('test');
        $h->observe(\PHP_FLOAT_MAX);

        try {
            $h->observe(\PHP_FLOAT_MAX);
        } catch (ObservabilityException) {
        }

        $this->assertSame(1, $h->count());
        $this->assertSame(\PHP_FLOAT_MAX, $h->sum());
    }

    #[Test]
    public function it_records_very_small_finite_observation(): void
    {
        $h = new Histogram('test');
        $h->observe(\PHP_FLOAT_MIN);

        $this->assertSame(1, $h->count());
        $this->assertSame(\PHP_FLOAT_MIN, $h->sum());
        $this->assertSame(\PHP_FLOAT_MIN, $h->minimum());
        $this->assertSame(\PHP_FLOAT_MIN, $h->maximum());
    }

    #[Test]
    public function it_preserves_state_after_rejected_positive_infinity(): void
    {
        $h = new Histogram('test');
        $h->observe(5.0);

        try {
            $h->observe(\INF);
        } catch (ObservabilityException) {
        }

        $this->assertSame(1, $h->count());
        $this->assertSame(5.0, $h->sum());
        $this->assertSame(5.0, $h->minimum());
        $this->assertSame(5.0, $h->maximum());
    }

    #[Test]
    public function it_preserves_state_after_rejected_negative_infinity(): void
    {
        $h = new Histogram('test');
        $h->observe(5.0);

        try {
            $h->observe(-\INF);
        } catch (ObservabilityException) {
        }

        $this->assertSame(1, $h->count());
        $this->assertSame(5.0, $h->sum());
        $this->assertSame(5.0, $h->minimum());
        $this->assertSame(5.0, $h->maximum());
    }

    // ── Name validation ──

    #[Test]
    public function it_accepts_valid_name(): void
    {
        $metric = new Histogram('http.requests');
        $this->assertSame('http.requests', $metric->name());
    }

    #[Test]
    public function it_accepts_name_with_dots_underscores_hyphens(): void
    {
        $metric = new Histogram('my-app.worker_1');
        $this->assertSame('my-app.worker_1', $metric->name());
    }

    #[Test]
    public function it_accepts_name_at_max_length(): void
    {
        $name = str_repeat('a', 128);
        $metric = new Histogram($name);
        $this->assertSame($name, $metric->name());
    }

    #[Test]
    public function it_rejects_empty_name(): void
    {
        $this->expectException(ObservabilityException::class);
        new Histogram('');
    }

    #[Test]
    public function it_rejects_name_over_128_chars(): void
    {
        $this->expectException(ObservabilityException::class);
        new Histogram(str_repeat('a', 129));
    }

    #[Test]
    public function it_rejects_name_with_spaces(): void
    {
        $this->expectException(ObservabilityException::class);
        new Histogram('my metric');
    }

    #[Test]
    public function it_rejects_name_with_tabs(): void
    {
        $this->expectException(ObservabilityException::class);
        new Histogram("my\tmetric");
    }

    #[Test]
    public function it_rejects_name_with_slash(): void
    {
        $this->expectException(ObservabilityException::class);
        new Histogram('my/metric');
    }

    #[Test]
    public function it_rejects_name_with_colon(): void
    {
        $this->expectException(ObservabilityException::class);
        new Histogram('my:metric');
    }

    #[Test]
    public function it_rejects_name_with_newline(): void
    {
        $this->expectException(ObservabilityException::class);
        new Histogram("my\nmetric");
    }

    #[Test]
    public function it_rejects_name_with_carriage_return(): void
    {
        $this->expectException(ObservabilityException::class);
        new Histogram("my\rmetric");
    }

    #[Test]
    public function it_rejects_name_with_control_char(): void
    {
        $this->expectException(ObservabilityException::class);
        new Histogram("my\x00metric");
    }

    #[Test]
    public function it_rejects_name_with_unicode_character(): void
    {
        $this->expectException(ObservabilityException::class);
        new Histogram('my_metric_🔥');
    }

    #[Test]
    public function it_rejects_name_with_trailing_newline(): void
    {
        $this->expectException(ObservabilityException::class);
        new Histogram("my_metric\n");
    }
}
