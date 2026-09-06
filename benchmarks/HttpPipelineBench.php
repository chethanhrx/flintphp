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

function bench(string $label, callable $fn, int $iterations): array
{
    // Warmup (JIT/opcache-free CLI is fine; this removes autoloader noise).
    for ($i = 0; $i < max(100, (int) ($iterations / 10)); $i++) {
        $fn();
    }

    $start = hrtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        $fn();
    }
    $elapsedNs = hrtime(true) - $start;

    return [
        'label' => $label,
        'ops' => $iterations,
        'per_op_us' => $elapsedNs / $iterations / 1000,
    ];
}

function report(array $results): void
{
    printf("%-38s %12s %14s\n", 'BENCHMARK', 'OPS', 'µS/OP');
    foreach ($results as $r) {
        printf("%-38s %12d %14.2f\n", $r['label'], $r['ops'], $r['per_op_us']);
    }
}

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
