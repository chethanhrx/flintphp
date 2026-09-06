<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Observability\Metrics;

use FlintPHP\Framework\Observability\Contract\MetricInterface;
use FlintPHP\Framework\Observability\Exception\ObservabilityException;
use FlintPHP\Framework\Observability\Metrics\Gauge;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Gauge::class)]
final class GaugeTest extends TestCase
{
    #[Test]
    public function it_implements_metric_interface(): void
    {
        $this->assertInstanceOf(MetricInterface::class, new Gauge('test'));
    }

    #[Test]
    public function it_starts_at_zero(): void
    {
        $gauge = new Gauge('test');
        $this->assertSame(0, $gauge->value());
    }

    #[Test]
    public function it_sets_positive_value(): void
    {
        $gauge = new Gauge('test');
        $gauge->set(42);
        $this->assertSame(42, $gauge->value());
    }

    #[Test]
    public function it_sets_negative_value(): void
    {
        $gauge = new Gauge('test');
        $gauge->set(-100);
        $this->assertSame(-100, $gauge->value());
    }

    #[Test]
    public function it_sets_zero(): void
    {
        $gauge = new Gauge('test');
        $gauge->set(50);
        $gauge->set(0);
        $this->assertSame(0, $gauge->value());
    }

    #[Test]
    public function it_sets_php_int_max(): void
    {
        $gauge = new Gauge('test');
        $gauge->set(\PHP_INT_MAX);
        $this->assertSame(\PHP_INT_MAX, $gauge->value());
    }

    #[Test]
    public function it_sets_php_int_min(): void
    {
        $gauge = new Gauge('test');
        $gauge->set(\PHP_INT_MIN);
        $this->assertSame(\PHP_INT_MIN, $gauge->value());
    }

    #[Test]
    public function it_increments(): void
    {
        $gauge = new Gauge('test');
        $gauge->increment();
        $this->assertSame(1, $gauge->value());
    }

    #[Test]
    public function it_increments_by_custom_amount(): void
    {
        $gauge = new Gauge('test');
        $gauge->increment(10);
        $this->assertSame(10, $gauge->value());
    }

    #[Test]
    public function it_decrements(): void
    {
        $gauge = new Gauge('test');
        $gauge->set(10);
        $gauge->decrement();
        $this->assertSame(9, $gauge->value());
    }

    #[Test]
    public function it_decrements_by_custom_amount(): void
    {
        $gauge = new Gauge('test');
        $gauge->set(10);
        $gauge->decrement(5);
        $this->assertSame(5, $gauge->value());
    }

    #[Test]
    public function it_accepts_increment_of_zero(): void
    {
        $gauge = new Gauge('test');
        $gauge->set(5);
        $gauge->increment(0);
        $this->assertSame(5, $gauge->value());
    }

    #[Test]
    public function it_accepts_decrement_of_zero(): void
    {
        $gauge = new Gauge('test');
        $gauge->set(5);
        $gauge->decrement(0);
        $this->assertSame(5, $gauge->value());
    }

    #[Test]
    public function it_rejects_negative_increment_amount(): void
    {
        $gauge = new Gauge('test');
        $this->expectException(ObservabilityException::class);
        $gauge->increment(-1);
    }

    #[Test]
    public function it_rejects_negative_decrement_amount(): void
    {
        $gauge = new Gauge('test');
        $this->expectException(ObservabilityException::class);
        $gauge->decrement(-1);
    }

    #[Test]
    public function it_rejects_increment_overflow(): void
    {
        $gauge = new Gauge('test');
        $gauge->set(\PHP_INT_MAX);

        $this->expectException(ObservabilityException::class);
        $this->expectExceptionMessage('overflow');
        $gauge->increment(1);
    }

    #[Test]
    public function it_preserves_value_after_failed_increment(): void
    {
        $gauge = new Gauge('test');
        $gauge->set(\PHP_INT_MAX);

        try {
            $gauge->increment(1);
        } catch (ObservabilityException) {
        }

        $this->assertSame(\PHP_INT_MAX, $gauge->value());
    }

    #[Test]
    public function it_rejects_decrement_underflow(): void
    {
        $gauge = new Gauge('test');
        $gauge->set(\PHP_INT_MIN);

        $this->expectException(ObservabilityException::class);
        $this->expectExceptionMessage('underflow');
        $gauge->decrement(1);
    }

    #[Test]
    public function it_preserves_value_after_failed_decrement(): void
    {
        $gauge = new Gauge('test');
        $gauge->set(\PHP_INT_MIN);

        try {
            $gauge->decrement(1);
        } catch (ObservabilityException) {
        }

        $this->assertSame(\PHP_INT_MIN, $gauge->value());
    }

    #[Test]
    public function it_succeeds_increment_at_boundary(): void
    {
        $gauge = new Gauge('test');
        $gauge->set(\PHP_INT_MAX - 1);
        $gauge->increment(1);
        $this->assertSame(\PHP_INT_MAX, $gauge->value());
    }

    #[Test]
    public function it_succeeds_decrement_at_boundary(): void
    {
        $gauge = new Gauge('test');
        $gauge->set(\PHP_INT_MIN + 1);
        $gauge->decrement(1);
        $this->assertSame(\PHP_INT_MIN, $gauge->value());
    }

    // ── Name validation ──

    #[Test]
    public function it_accepts_valid_name(): void
    {
        $metric = new Gauge('http.requests');
        $this->assertSame('http.requests', $metric->name());
    }

    #[Test]
    public function it_accepts_name_with_dots_underscores_hyphens(): void
    {
        $metric = new Gauge('my-app.worker_1');
        $this->assertSame('my-app.worker_1', $metric->name());
    }

    #[Test]
    public function it_accepts_name_at_max_length(): void
    {
        $name = str_repeat('a', 128);
        $metric = new Gauge($name);
        $this->assertSame($name, $metric->name());
    }

    #[Test]
    public function it_rejects_empty_name(): void
    {
        $this->expectException(ObservabilityException::class);
        new Gauge('');
    }

    #[Test]
    public function it_rejects_name_over_128_chars(): void
    {
        $this->expectException(ObservabilityException::class);
        new Gauge(str_repeat('a', 129));
    }

    #[Test]
    public function it_rejects_name_with_spaces(): void
    {
        $this->expectException(ObservabilityException::class);
        new Gauge('my metric');
    }

    #[Test]
    public function it_rejects_name_with_tabs(): void
    {
        $this->expectException(ObservabilityException::class);
        new Gauge("my\tmetric");
    }

    #[Test]
    public function it_rejects_name_with_slash(): void
    {
        $this->expectException(ObservabilityException::class);
        new Gauge('my/metric');
    }

    #[Test]
    public function it_rejects_name_with_colon(): void
    {
        $this->expectException(ObservabilityException::class);
        new Gauge('my:metric');
    }

    #[Test]
    public function it_rejects_name_with_newline(): void
    {
        $this->expectException(ObservabilityException::class);
        new Gauge("my\nmetric");
    }

    #[Test]
    public function it_rejects_name_with_carriage_return(): void
    {
        $this->expectException(ObservabilityException::class);
        new Gauge("my\rmetric");
    }

    #[Test]
    public function it_rejects_name_with_control_char(): void
    {
        $this->expectException(ObservabilityException::class);
        new Gauge("my\x00metric");
    }

    #[Test]
    public function it_rejects_name_with_unicode_character(): void
    {
        $this->expectException(ObservabilityException::class);
        new Gauge('my_metric_🔥');
    }

    #[Test]
    public function it_rejects_name_with_trailing_newline(): void
    {
        $this->expectException(ObservabilityException::class);
        new Gauge("my_metric\n");
    }
}
