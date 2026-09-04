<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Security;

use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;
use FlintPHP\Framework\Security\Middleware\SecurityHeadersConfiguration;
use FlintPHP\Framework\Security\Middleware\SecurityHeadersMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SecurityHeadersMiddleware::class)]
#[CoversClass(SecurityHeadersConfiguration::class)]
final class SecurityHeadersMiddlewareTest extends TestCase
{
    #[Test]
    public function it_adds_default_security_headers(): void
    {
        $config = new SecurityHeadersConfiguration();
        $middleware = new SecurityHeadersMiddleware($config);

        $request = new Request('GET', '/');
        
        $next = function (Request $request) {
            return new Response('OK', 200);
        };

        $response = $middleware->process($request, $next);

        $this->assertSame('nosniff', $response->header('X-Content-Type-Options'));
        $this->assertSame('DENY', $response->header('X-Frame-Options'));
        $this->assertSame('strict-origin-when-cross-origin', $response->header('Referrer-Policy'));
        $this->assertNull($response->header('Content-Security-Policy'));
        $this->assertNull($response->header('Strict-Transport-Security'));
    }

    #[Test]
    public function it_adds_csp_when_configured(): void
    {
        $config = (new SecurityHeadersConfiguration())
            ->withContentSecurityPolicy("default-src 'self'");
            
        $middleware = new SecurityHeadersMiddleware($config);

        $request = new Request('GET', '/');
        $next = function (Request $request) {
            return new Response('OK', 200);
        };

        $response = $middleware->process($request, $next);

        $this->assertSame("default-src 'self'", $response->header('Content-Security-Policy'));
    }

    #[Test]
    public function it_adds_hsts_when_configured(): void
    {
        $config = (new SecurityHeadersConfiguration())
            ->withStrictTransportSecurity(31536000, true, true);
            
        $middleware = new SecurityHeadersMiddleware($config);

        $request = new Request('GET', '/');
        $next = function (Request $request) {
            return new Response('OK', 200);
        };

        $response = $middleware->process($request, $next);

        $this->assertSame('max-age=31536000; includeSubDomains; preload', $response->header('Strict-Transport-Security'));
    }

    #[Test]
    public function it_preserves_existing_security_headers(): void
    {
        $config = (new SecurityHeadersConfiguration())
            ->withContentSecurityPolicy("default-src 'self'")
            ->withStrictTransportSecurity(31536000);
            
        $middleware = new SecurityHeadersMiddleware($config);

        $request = new Request('GET', '/');
        $next = function (Request $request) {
            $response = new Response('OK', 200);
            return $response->withHeader('X-Frame-Options', 'SAMEORIGIN')
                            ->withHeader('x-content-type-options', 'custom-value')
                            ->withHeader('Content-Security-Policy', "default-src 'none'");
        };

        $response = $middleware->process($request, $next);

        // Pre-existing headers are preserved
        $this->assertSame('SAMEORIGIN', $response->header('X-Frame-Options'));
        $this->assertSame('custom-value', $response->header('X-Content-Type-Options'));
        $this->assertSame("default-src 'none'", $response->header('Content-Security-Policy'));

        // Missing headers get defaults
        $this->assertSame('strict-origin-when-cross-origin', $response->header('Referrer-Policy'));
        $this->assertSame('max-age=31536000', $response->header('Strict-Transport-Security'));
    }
}
