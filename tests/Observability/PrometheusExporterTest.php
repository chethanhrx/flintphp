<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Observability;

use FlintPHP\Framework\Observability\Metrics\MetricRegistry;
use FlintPHP\Framework\Observability\Metrics\PrometheusExporter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PrometheusExporterTest extends TestCase
{
    #[Test]
    public function exports_counter_and_gauge(): void
    {
        $registry = new MetricRegistry();
        $registry->counter('http_requests_total')->increment(3);
        $registry->gauge('queue_size')->set(7);

        $text = (new PrometheusExporter($registry))->export();

        $this->assertStringContainsString('http_requests_total 3', $text);
        $this->assertStringContainsString('queue_size 7', $text);
        $this->assertStringContainsString('# TYPE http_requests_total counter', $text);
    }
}
