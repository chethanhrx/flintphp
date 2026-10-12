<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Http\Middleware;

use FlintPHP\Framework\Http\Middleware\CorsMiddleware;
use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CorsMiddleware::class)]
final class CorsMiddlewareTest extends TestCase
{
    #[Test]
    public function wildcard_mode_without_credentials_sets_wildcard_allow_origin(): void
    {
        $mw = new CorsMiddleware(allowedOrigins: ['*'], allowCredentials: false);
        $req = new Request('GET', '/api', headers: ['Origin' => 'https://random-client.org']);

        $res = $mw->process($req, fn () => new Response('data'));

        $this->assertSame('*', $res->header('Access-Control-Allow-Origin'));
        $this->assertNull($res->header('Access-Control-Allow-Credentials'));
        $this->assertSame('Origin', $res->header('Vary'));
    }

    #[Test]
    public function credentials_with_explicit_origin_reflects_only_matching_origin(): void
    {
        $mw = new CorsMiddleware(
            allowedOrigins: ['https://app.example.com'],
            allowCredentials: true
        );
        $req = new Request('GET', '/api', headers: ['Origin' => 'https://app.example.com']);

        $res = $mw->process($req, fn () => new Response('secure data'));

        $this->assertSame('https://app.example.com', $res->header('Access-Control-Allow-Origin'));
        $this->assertSame('true', $res->header('Access-Control-Allow-Credentials'));
    }

    #[Test]
    public function rejects_combining_wildcard_origins_with_credentials(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot combine wildcard allowedOrigins ["*"] with allowCredentials = true');

        new CorsMiddleware(allowedOrigins: ['*'], allowCredentials: true);
    }

    #[Test]
    public function disallowed_origin_receives_no_cors_headers(): void
    {
        $mw = new CorsMiddleware(allowedOrigins: ['https://trusted.com']);
        $req = new Request('GET', '/api', headers: ['Origin' => 'https://evil.com']);

        $res = $mw->process($req, fn () => new Response('data'));

        $this->assertNull($res->header('Access-Control-Allow-Origin'));
        $this->assertNull($res->header('Access-Control-Allow-Methods'));
        $this->assertNull($res->header('Access-Control-Allow-Credentials'));
    }

    #[Test]
    public function spoofed_origin_substrings_are_strictly_rejected(): void
    {
        $mw = new CorsMiddleware(allowedOrigins: ['https://trusted.com']);

        $spoofedReq1 = new Request('GET', '/api', headers: ['Origin' => 'https://trusted.com.attacker.com']);
        $res1 = $mw->process($spoofedReq1, fn () => new Response('data'));
        $this->assertNull($res1->header('Access-Control-Allow-Origin'));

        $spoofedReq2 = new Request('GET', '/api', headers: ['Origin' => 'http://trusted.com']); // wrong scheme
        $res2 = $mw->process($spoofedReq2, fn () => new Response('data'));
        $this->assertNull($res2->header('Access-Control-Allow-Origin'));

        $spoofedReq3 = new Request('GET', '/api', headers: ['Origin' => 'https://trusted.com:8443']); // wrong port
        $res3 = $mw->process($spoofedReq3, fn () => new Response('data'));
        $this->assertNull($res3->header('Access-Control-Allow-Origin'));
    }

    #[Test]
    public function preflight_request_with_allowed_method_and_headers_returns_204(): void
    {
        $mw = new CorsMiddleware(
            allowedOrigins: ['https://trusted.com'],
            allowedMethods: ['POST', 'PUT'],
            allowedHeaders: ['Content-Type', 'X-Requested-With'],
            maxAge: 3600,
            allowCredentials: true
        );

        $preflightReq = new Request('OPTIONS', '/api', headers: [
            'Origin' => 'https://trusted.com',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'Content-Type, X-Requested-With',
        ]);

        $res = $mw->process($preflightReq, fn () => new Response('should not be reached'));

        $this->assertSame(204, $res->status());
        $this->assertSame('https://trusted.com', $res->header('Access-Control-Allow-Origin'));
        $this->assertSame('POST, PUT', $res->header('Access-Control-Allow-Methods'));
        $this->assertSame('Content-Type, X-Requested-With', $res->header('Access-Control-Allow-Headers'));
        $this->assertSame('3600', $res->header('Access-Control-Max-Age'));
        $this->assertSame('true', $res->header('Access-Control-Allow-Credentials'));
    }

    #[Test]
    public function preflight_request_with_disallowed_method_returns_403(): void
    {
        $mw = new CorsMiddleware(
            allowedOrigins: ['https://trusted.com'],
            allowedMethods: ['GET', 'POST']
        );

        $preflightReq = new Request('OPTIONS', '/api', headers: [
            'Origin' => 'https://trusted.com',
            'Access-Control-Request-Method' => 'DELETE',
        ]);

        $res = $mw->process($preflightReq, fn () => new Response(''));

        $this->assertSame(403, $res->status());
        $this->assertNull($res->header('Access-Control-Allow-Origin'));
    }

    #[Test]
    public function preflight_request_with_disallowed_header_returns_403(): void
    {
        $mw = new CorsMiddleware(
            allowedOrigins: ['https://trusted.com'],
            allowedHeaders: ['Content-Type']
        );

        $preflightReq = new Request('OPTIONS', '/api', headers: [
            'Origin' => 'https://trusted.com',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'X-Evil-Header',
        ]);

        $res = $mw->process($preflightReq, fn () => new Response(''));

        $this->assertSame(403, $res->status());
        $this->assertNull($res->header('Access-Control-Allow-Origin'));
    }

    #[Test]
    public function preserves_existing_vary_headers(): void
    {
        $mw = new CorsMiddleware(allowedOrigins: ['https://trusted.com']);
        $req = new Request('GET', '/api', headers: ['Origin' => 'https://trusted.com']);

        $res = $mw->process($req, function () {
            return (new Response('data'))->withHeader('Vary', 'Accept-Encoding, Authorization');
        });

        $this->assertSame('Accept-Encoding, Authorization, Origin', $res->header('Vary'));
    }

    #[Test]
    public function does_not_duplicate_existing_vary_origin(): void
    {
        $mw = new CorsMiddleware(allowedOrigins: ['https://trusted.com']);
        $req = new Request('GET', '/api', headers: ['Origin' => 'https://trusted.com']);

        $res = $mw->process($req, function () {
            return (new Response('data'))->withHeader('Vary', 'Origin');
        });

        $this->assertSame('Origin', $res->header('Vary'));
    }

    #[Test]
    public function preserves_existing_response_headers(): void
    {
        $mw = new CorsMiddleware(allowedOrigins: ['https://trusted.com']);
        $req = new Request('GET', '/api', headers: ['Origin' => 'https://trusted.com']);

        $res = $mw->process($req, function () {
            return (new Response('data'))
                ->withHeader('X-Custom-Header', 'custom-value')
                ->withHeader('Content-Type', 'application/json');
        });

        $this->assertSame('custom-value', $res->header('X-Custom-Header'));
        $this->assertSame('application/json', $res->header('Content-Type'));
        $this->assertSame('https://trusted.com', $res->header('Access-Control-Allow-Origin'));
    }

    #[Test]
    public function non_preflight_options_without_origin_passes_through_to_next(): void
    {
        $mw = new CorsMiddleware(allowedOrigins: ['https://trusted.com']);
        $req = new Request('OPTIONS', '/api'); // No Origin header

        $res = $mw->process($req, fn () => new Response('native options response', 200));

        $this->assertSame(200, $res->status());
        $this->assertSame('native options response', $res->body());
    }

    #[Test]
    public function rejects_invalid_configuration(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CorsMiddleware(maxAge: -1);
    }

    #[Test]
    public function rejects_empty_origins_configuration(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CorsMiddleware(allowedOrigins: []);
    }

    #[Test]
    public function rejects_empty_string_origin(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CorsMiddleware(allowedOrigins: ['']);
    }
}
