<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Http\Middleware;

use FlintPHP\Framework\Cache\ArrayCache;
use FlintPHP\Framework\Http\Middleware\CorsMiddleware;
use FlintPHP\Framework\Http\Middleware\RateLimitMiddleware;
use FlintPHP\Framework\Http\Middleware\RequestIdMiddleware;
use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EnterpriseMiddlewareTest extends TestCase
{
    #[Test]
    public function request_id_is_set_on_request_and_response(): void
    {
        $mw = new RequestIdMiddleware();
        $captured = null;

        $response = $mw->process(new Request('GET', '/'), function (Request $req) use (&$captured): Response {
            $captured = $req->attribute(RequestIdMiddleware::ATTRIBUTE);

            return new Response('ok');
        });

        $this->assertNotNull($captured);
        $this->assertSame($captured, $response->header(RequestIdMiddleware::HEADER));
    }

    #[Test]
    public function cors_adds_headers_for_allowed_origin(): void
    {
        $mw = new CorsMiddleware(allowedOrigins: ['https://app.example.com']);
        $request = new Request('GET', '/api', headers: ['Origin' => 'https://app.example.com']);

        $response = $mw->process($request, fn () => new Response('ok'));

        $this->assertSame('https://app.example.com', $response->header('Access-Control-Allow-Origin'));
    }

    #[Test]
    public function rate_limit_returns_429_when_exceeded(): void
    {
        $cache = new ArrayCache();
        $mw = new RateLimitMiddleware($cache, maxAttempts: 2, windowSeconds: 60);
        $request = new Request('GET', '/', clientIp: '1.2.3.4');

        $mw->process($request, fn () => new Response('ok'));
        $mw->process($request, fn () => new Response('ok'));
        $blocked = $mw->process($request, fn () => new Response('ok'));

        $this->assertSame(429, $blocked->status());
    }
}
