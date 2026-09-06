<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Observability\Metrics;

use FlintPHP\Framework\Observability\Contract\MetricRegistryInterface;
use FlintPHP\Framework\Observability\Exception\ObservabilityException;
use FlintPHP\Framework\Observability\Metrics\Counter;
use FlintPHP\Framework\Observability\Metrics\Gauge;
use FlintPHP\Framework\Observability\Metrics\Histogram;
use FlintPHP\Framework\Observability\Metrics\MetricRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MetricRegistry::class)]
final class MetricRegistryTest extends TestCase
{
    private MetricRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new MetricRegistry();
    }

    #[Test]
    public function it_implements_registry_interface(): void
    {
        $this->assertInstanceOf(MetricRegistryInterface::class, $this->registry);
    }

    // ── Creation ──

    #[Test]
    public function it_creates_counter(): void
    {
        $counter = $this->registry->counter('http.requests');
        $this->assertInstanceOf(Counter::class, $counter);
        $this->assertSame('http.requests', $counter->name());
    }

    #[Test]
    public function it_creates_gauge(): void
    {
        $gauge = $this->registry->gauge('active.connections');
        $this->assertInstanceOf(Gauge::class, $gauge);
        $this->assertSame('active.connections', $gauge->name());
    }

    #[Test]
    public function it_creates_histogram(): void
    {
        $histogram = $this->registry->histogram('response.time');
        $this->assertInstanceOf(Histogram::class, $histogram);
        $this->assertSame('response.time', $histogram->name());
    }

    // ── Same-instance semantics ──

    #[Test]
    public function it_returns_same_counter_on_repeated_lookup(): void
    {
        $a = $this->registry->counter('requests');
        $b = $this->registry->counter('requests');
        $this->assertSame($a, $b);
    }

    #[Test]
    public function it_returns_same_gauge_on_repeated_lookup(): void
    {
        $a = $this->registry->gauge('connections');
        $b = $this->registry->gauge('connections');
        $this->assertSame($a, $b);
    }

    #[Test]
    public function it_returns_same_histogram_on_repeated_lookup(): void
    {
        $a = $this->registry->histogram('latency');
        $b = $this->registry->histogram('latency');
        $this->assertSame($a, $b);
    }

    // ── Cross-type collision ──

    #[Test]
    public function it_rejects_counter_gauge_collision(): void
    {
        $this->registry->counter('requests');
        $this->expectException(ObservabilityException::class);
        $this->expectExceptionMessage('already registered');
        $this->registry->gauge('requests');
    }

    #[Test]
    public function it_rejects_counter_histogram_collision(): void
    {
        $this->registry->counter('requests');
        $this->expectException(ObservabilityException::class);
        $this->registry->histogram('requests');
    }

    #[Test]
    public function it_rejects_gauge_counter_collision(): void
    {
        $this->registry->gauge('requests');
        $this->expectException(ObservabilityException::class);
        $this->registry->counter('requests');
    }

    #[Test]
    public function it_rejects_gauge_histogram_collision(): void
    {
        $this->registry->gauge('requests');
        $this->expectException(ObservabilityException::class);
        $this->registry->histogram('requests');
    }

    #[Test]
    public function it_rejects_histogram_counter_collision(): void
    {
        $this->registry->histogram('requests');
        $this->expectException(ObservabilityException::class);
        $this->registry->counter('requests');
    }

    #[Test]
    public function it_rejects_histogram_gauge_collision(): void
    {
        $this->registry->histogram('requests');
        $this->expectException(ObservabilityException::class);
        $this->registry->gauge('requests');
    }

    // ── has() ──

    #[Test]
    public function has_returns_false_for_unregistered(): void
    {
        $this->assertFalse($this->registry->has('nonexistent'));
    }

    #[Test]
    public function has_returns_true_after_registration(): void
    {
        $this->registry->counter('requests');
        $this->assertTrue($this->registry->has('requests'));
    }

    #[Test]
    public function has_throws_for_invalid_name(): void
    {
        $this->expectException(ObservabilityException::class);
        $this->registry->has('');
    }

    // ── all() ──

    #[Test]
    public function all_returns_empty_initially(): void
    {
        $this->assertSame([], $this->registry->all());
    }

    #[Test]
    public function all_returns_registered_metrics(): void
    {
        $counter = $this->registry->counter('a');
        $gauge = $this->registry->gauge('b');

        $all = $this->registry->all();
        $this->assertCount(2, $all);
        $this->assertSame($counter, $all['a']);
        $this->assertSame($gauge, $all['b']);
    }

    #[Test]
    public function all_returns_snapshot_that_cannot_modify_registry(): void
    {
        $counter = $this->registry->counter('a');
        $gauge = $this->registry->gauge('b');

        $all = $this->registry->all();

        // Modifying existing
        $all['a'] = new Counter('injected');
        // Adding new
        $all['new'] = new Gauge('new');
        // Unsetting
        unset($all['b']);

        // Registry is unaffected
        $this->assertCount(2, $this->registry->all());
        $this->assertSame($counter, $this->registry->counter('a'));
        $this->assertSame($gauge, $this->registry->gauge('b'));
        $this->assertFalse($this->registry->has('new'));
    }

    // ── clear() ──

    #[Test]
    public function clear_removes_all_metrics(): void
    {
        $this->registry->counter('a');
        $this->registry->gauge('b');
        $this->assertCount(2, $this->registry->all());

        $this->registry->clear();
        $this->assertCount(0, $this->registry->all());
        $this->assertFalse($this->registry->has('a'));
        $this->assertFalse($this->registry->has('b'));
    }

    #[Test]
    public function old_metric_is_detached_after_clear(): void
    {
        $old = $this->registry->counter('requests');
        $old->increment(5);

        $this->registry->clear();

        $new = $this->registry->counter('requests');
        $this->assertNotSame($old, $new);
        $this->assertSame(0, $new->value());
        $this->assertSame(5, $old->value()); // Old object persists independently
    }

    #[Test]
    public function clear_allows_different_type_for_same_name(): void
    {
        $this->registry->counter('requests');
        $this->registry->clear();

        // Should now accept a gauge with the same name
        $gauge = $this->registry->gauge('requests');
        $this->assertInstanceOf(Gauge::class, $gauge);
    }

    // ── Instance isolation ──

    #[Test]
    public function separate_registries_are_isolated(): void
    {
        $a = new MetricRegistry();
        $b = new MetricRegistry();

        $a->counter('requests');
        $this->assertTrue($a->has('requests'));
        $this->assertFalse($b->has('requests'));
    }

    // ── Invalid names ──

    /**
     * @return array<string, array{string}>
     */
    public static function invalidNameProvider(): array
    {
        return [
            'empty' => [''],
            'over 128 chars' => [str_repeat('a', 129)],
            'spaces' => ['my metric'],
            'tabs' => ["my\tmetric"],
            'slash' => ['my/metric'],
            'colon' => ['my:metric'],
            'newline' => ["my\nmetric"],
            'carriage return' => ["my\rmetric"],
            'control char' => ["my\x00metric"],
            'unicode' => ['my_metric_🔥'],
            'trailing newline' => ["my_metric\n"],
        ];
    }

    #[Test]
    #[DataProvider('invalidNameProvider')]
    public function it_rejects_invalid_names_in_counter(string $invalidName): void
    {
        $this->expectException(ObservabilityException::class);
        $this->registry->counter($invalidName);
    }

    #[Test]
    #[DataProvider('invalidNameProvider')]
    public function it_rejects_invalid_names_in_gauge(string $invalidName): void
    {
        $this->expectException(ObservabilityException::class);
        $this->registry->gauge($invalidName);
    }

    #[Test]
    #[DataProvider('invalidNameProvider')]
    public function it_rejects_invalid_names_in_histogram(string $invalidName): void
    {
        $this->expectException(ObservabilityException::class);
        $this->registry->histogram($invalidName);
    }

    #[Test]
    #[DataProvider('invalidNameProvider')]
    public function it_rejects_invalid_names_in_has(string $invalidName): void
    {
        $this->expectException(ObservabilityException::class);
        $this->registry->has($invalidName);
    }
}
