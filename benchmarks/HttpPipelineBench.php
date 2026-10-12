<?php

declare(strict_types=1);

/**
 * FlintPHP v1 performance gate benchmark.
 *
 * Measures the real framework through public APIs only. Intentionally simple
 * and dependency-free. Run: php benchmarks/HttpPipelineBench.php
 */

require __DIR__ . '/../vendor/autoload.php';

use FlintPHP\Framework\Foundation\Application;
use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;

// Supporting middleware, declared before execution so class_exists() and
// container resolution always see them.
final class TagMiddleware implements \FlintPHP\Framework\Middleware\MiddlewareInterface
{
    public function process(\FlintPHP\Framework\Http\Request $request, callable $next): \FlintPHP\Framework\Http\Response
    {
        return $next($request->withAttribute('tagged', true));
    }
}

final class CounterMiddleware implements \FlintPHP\Framework\Middleware\MiddlewareInterface
{
    public function process(\FlintPHP\Framework\Http\Request $request, callable $next): \FlintPHP\Framework\Http\Response
    {
        return $next($request->withAttribute('count', 1));
    }
}

final class ScopedTagMiddleware implements \FlintPHP\Framework\Middleware\MiddlewareInterface
{
    public function process(\FlintPHP\Framework\Http\Request $request, callable $next): \FlintPHP\Framework\Http\Response
    {
        return $next($request->withAttribute('scoped', true));
    }
}

function printEnvironment(): void
{
    echo "=======================================================================\n";
    echo " FLINTPHP BENCHMARK HARNESS\n";
    echo "=======================================================================\n";
    echo "PHP Version:    " . PHP_VERSION . " (" . PHP_SAPI . ")\n";
    echo "OS / Kernel:    " . PHP_OS . " (" . php_uname('m') . ")\n";
    $opcache = function_exists('opcache_get_status') && is_array(opcache_get_status(false));
    echo "OPcache:        " . ($opcache ? 'Enabled' : 'Disabled / CLI default') . "\n";
    echo "JIT:            " . (ini_get('opcache.jit') ? ini_get('opcache.jit') : 'Disabled') . "\n";
    echo "Memory Limit:   " . ini_get('memory_limit') . "\n";
    echo "=======================================================================\n\n";
}

function bench(string $label, callable $fn, int $iterations): array
{
    // Warmup procedure to eliminate autoloader and cache warmup noise
    $warmupCount = max(100, (int) ($iterations / 10));
    for ($i = 0; $i < $warmupCount; $i++) {
        $fn();
    }

    $samples = [];
    $sampleCount = min(2000, $iterations);
    $batchSize = max(1, (int) ($iterations / $sampleCount));

    $startTotal = hrtime(true);
    for ($s = 0; $s < $sampleCount; $s++) {
        $t0 = hrtime(true);
        for ($b = 0; $b < $batchSize; $b++) {
            $fn();
        }
        $samples[] = (hrtime(true) - $t0) / $batchSize / 1000.0; // us per op
    }
    $totalElapsedNs = hrtime(true) - $startTotal;
    $actualOps = $sampleCount * $batchSize;

    sort($samples);
    $p50 = $samples[(int) ($sampleCount * 0.50)];
    $p95 = $samples[(int) ($sampleCount * 0.95)];
    $p99 = $samples[(int) ($sampleCount * 0.99)];
    $meanUs = ($totalElapsedNs / $actualOps) / 1000.0;
    $opsPerSec = $actualOps / ($totalElapsedNs / 1_000_000_000.0);

    return [
        'label' => $label,
        'ops' => $actualOps,
        'ops_per_sec' => $opsPerSec,
        'mean_us' => $meanUs,
        'p50_us' => $p50,
        'p95_us' => $p95,
        'p99_us' => $p99,
    ];
}

function report(array $results): void
{
    printf(
        "%-36s %10s %12s %10s %10s %10s %10s\n",
        'BENCHMARK', 'OPS', 'THROUGHPUT', 'MEAN (µs)', 'P50 (µs)', 'P95 (µs)', 'P99 (µs)'
    );
    printf("%s\n", str_repeat('-', 104));
    foreach ($results as $r) {
        printf(
            "%-36s %10d %10.0f/s %10.2f %10.2f %10.2f %10.2f\n",
            $r['label'],
            $r['ops'],
            $r['ops_per_sec'],
            $r['mean_us'],
            $r['p50_us'],
            $r['p95_us'],
            $r['p99_us']
        );
    }
    echo "\n";
}

printEnvironment();

// ---------------------------------------------------------------- Request --
$request = new Request('GET', '/users/42/posts/7', ['Accept' => 'application/json']);
$results = [];
$results[] = bench('Request construction', fn (): Request => new Request('GET', '/users/42/posts/7'), 50000);
$results[] = bench('Request path() + method()', function () use ($request): string {
    return $request->path() . $request->method();
}, 200000);

// ------------------------------------------------------------------ Router --
$app = new Application('/tmp');
$app->router()->get('/static', fn (): Response => new Response('ok'));
$app->router()->get('/users/{id}/posts/{postId}', function (Request $req, int $id, int $postId): Response {
    return new Response("{$id}-{$postId}");
});
$app->router()->post('/users', fn (): Response => new Response('created'));
$app->router()->get('/missing', fn (): Response => new Response('nope'));

$results[] = bench('Static route match + dispatch', function () use ($app): void {
    $app->kernel()->handle(new Request('GET', '/static'));
}, 20000);

$results[] = bench('Dynamic route (2 params) dispatch', function () use ($app): void {
    $app->kernel()->handle(new Request('GET', '/users/42/posts/7'));
}, 20000);

// ------------------------------------------------- Global middleware (3x) --
$mwApp = new Application('/tmp');

$mwApp->addMiddleware(TagMiddleware::class);
$mwApp->addMiddleware(CounterMiddleware::class);
$mwApp->addMiddleware(TagMiddleware::class);

$mwApp->router()->get('/mw', fn (): Response => new Response('ok'));
$mwApp->router()->get('/unprotected', fn (): Response => new Response('ok'));

$results[] = bench('Unscoped route through 3 global MW', function () use ($mwApp): void {
    $mwApp->kernel()->handle(new Request('GET', '/mw'));
}, 20000);

// --------------------------------------------- Scoped middleware (authn) --
$scopedApp = new Application('/tmp');
$scopedApp->router()->get('/scoped', fn (): Response => new Response('ok'), middleware: [ScopedTagMiddleware::class]);

$results[] = bench('Scoped route with 1 scoped MW', function () use ($scopedApp): void {
    $scopedApp->kernel()->handle(new Request('GET', '/scoped'));
}, 20000);

// ---------------------------------------------------------------- Response --
$results[] = bench('Response json() encode', fn (): Response => Response::json(['users' => range(1, 20)]), 50000);

report($results);
