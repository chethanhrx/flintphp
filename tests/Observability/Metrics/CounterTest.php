<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Observability\Metrics;

use FlintPHP\Framework\Observability\Contract\MetricInterface;
use FlintPHP\Framework\Observability\Exception\ObservabilityException;
use FlintPHP\Framework\Observability\Metrics\Counter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Counter::class)]
final class CounterTest extends TestCase
{
    // ── Name validation ──

    #[Test]
    public function it_accepts_valid_name(): void
    {
        $counter = new Counter('http.requests');
        $this->assertSame('http.requests', $counter->name());
    }

    #[Test]
    public function it_implements_metric_interface(): void
    {
        $this->assertInstanceOf(MetricInterface::class, new Counter('test'));
    }

    #[Test]
    public function it_accepts_name_with_dots_underscores_hyphens(): void
    {
        $counter = new Counter('my-app.worker_1');
        $this->assertSame('my-app.worker_1', $counter->name());
    }

    #[Test]
    public function it_accepts_name_at_max_length(): void
    {
        $name = str_repeat('a', 128);
        $counter = new Counter($name);
        $this->assertSame($name, $counter->name());
    }

    #[Test]
    public function it_rejects_empty_name(): void
    {
        $this->expectException(ObservabilityException::class);
        new Counter('');
    }

    #[Test]
    public function it_rejects_name_over_128_chars(): void
    {
        $this->expectException(ObservabilityException::class);
        new Counter(str_repeat('a', 129));
    }

    #[Test]
    public function it_rejects_name_with_spaces(): void
    {
        $this->expectException(ObservabilityException::class);
        new Counter('my counter');
    }

    #[Test]
    public function it_rejects_name_with_tabs(): void
    {
        $this->expectException(ObservabilityException::class);
        new Counter("my\tcounter");
    }

    #[Test]
    public function it_rejects_name_with_slash(): void
    {
        $this->expectException(ObservabilityException::class);
        new Counter('my/counter');
    }

    #[Test]
    public function it_rejects_name_with_colon(): void
    {
        $this->expectException(ObservabilityException::class);
        new Counter('my:counter');
    }

    #[Test]
    public function it_rejects_name_with_newline(): void
    {
        $this->expectException(ObservabilityException::class);
        new Counter("my\ncounter");
    }

    #[Test]
    public function it_rejects_name_with_carriage_return(): void
    {
        $this->expectException(ObservabilityException::class);
        new Counter("my\rcounter");
    }

    #[Test]
    public function it_rejects_name_with_control_char(): void
    {
        $this->expectException(ObservabilityException::class);
        new Counter("my\x00counter");
    }

    #[Test]
    public function it_rejects_name_with_unicode_character(): void
    {
        $this->expectException(ObservabilityException::class);
        new Counter('my_counter_🔥');
    }

    #[Test]
    public function it_rejects_name_with_trailing_newline(): void
    {
        $this->expectException(ObservabilityException::class);
        new Counter("my_counter\n");
    }

    // ── Counter semantics ──

    #[Test]
    public function it_starts_at_zero(): void
    {
        $counter = new Counter('test');
        $this->assertSame(0, $counter->value());
    }

    #[Test]
    public function it_increments_by_one(): void
    {
        $counter = new Counter('test');
        $counter->increment();
        $this->assertSame(1, $counter->value());
    }

    #[Test]
    public function it_increments_by_custom_amount(): void
    {
        $counter = new Counter('test');
        $counter->increment(5);
        $this->assertSame(5, $counter->value());
    }

    #[Test]
    public function it_accumulates_multiple_increments(): void
    {
        $counter = new Counter('test');
        $counter->increment(3);
        $counter->increment(7);
        $counter->increment(1);
        $this->assertSame(11, $counter->value());
    }

    #[Test]
    public function it_accepts_increment_of_zero(): void
    {
        $counter = new Counter('test');
        $counter->increment(0);
        $this->assertSame(0, $counter->value());
    }

    #[Test]
    public function it_rejects_negative_increment(): void
    {
        $counter = new Counter('test');
        $counter->increment(5);

        $this->expectException(ObservabilityException::class);
        $this->expectExceptionMessage('non-negative');
        $counter->increment(-1);
    }

    #[Test]
    public function it_preserves_value_after_rejected_negative_increment(): void
    {
        $counter = new Counter('test');
        $counter->increment(5);

        try {
            $counter->increment(-1);
        } catch (ObservabilityException) {
        }

        $this->assertSame(5, $counter->value());
    }

    #[Test]
    public function it_rejects_overflow(): void
    {
        $counter = new Counter('test');
        $counter->increment(\PHP_INT_MAX - 1);

        // This should succeed (value becomes PHP_INT_MAX - 1 + 1 = PHP_INT_MAX)
        $counter->increment(1);
        $this->assertSame(\PHP_INT_MAX, $counter->value());

        // This should fail
        $this->expectException(ObservabilityException::class);
        $this->expectExceptionMessage('overflow');
        $counter->increment(1);
    }

    #[Test]
    public function it_preserves_value_after_rejected_overflow(): void
    {
        $counter = new Counter('test');
        $counter->increment(\PHP_INT_MAX);

        try {
            $counter->increment(1);
        } catch (ObservabilityException) {
        }

        $this->assertSame(\PHP_INT_MAX, $counter->value());
    }
}
