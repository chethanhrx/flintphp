<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Observability\Metrics;

use FlintPHP\Framework\Http\Response;
use FlintPHP\Framework\Observability\Contract\MetricRegistryInterface;

/**
 * Export MetricRegistry snapshot as Prometheus text exposition format.
 */
final class PrometheusExporter
{
    public function __construct(
        private readonly MetricRegistryInterface $registry,
    ) {
    }

    public function export(): string
    {
        $lines = [];

        foreach ($this->registry->all() as $metric) {
            $name = $this->sanitize($metric->name());

            if ($metric instanceof Counter) {
                $lines[] = '# TYPE ' . $name . ' counter';
                $lines[] = $name . ' ' . $metric->value();
            } elseif ($metric instanceof Gauge) {
                $lines[] = '# TYPE ' . $name . ' gauge';
                $lines[] = $name . ' ' . $metric->value();
            } elseif ($metric instanceof Histogram) {
                $lines[] = '# TYPE ' . $name . ' summary';
                $lines[] = $name . '_count ' . $metric->count();
                $lines[] = $name . '_sum ' . $metric->sum();
                if ($metric->count() > 0) {
                    $lines[] = $name . '_min ' . $metric->minimum();
                    $lines[] = $name . '_max ' . $metric->maximum();
                }
            }
        }

        return implode("\n", $lines) . (count($lines) > 0 ? "\n" : '');
    }

    public function toResponse(): Response
    {
        return new Response(
            body: $this->export(),
            status: 200,
            headers: ['Content-Type' => 'text/plain; version=0.0.4; charset=utf-8'],
        );
    }

    private function sanitize(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9_:]/', '_', $name) ?? $name;
    }
}
