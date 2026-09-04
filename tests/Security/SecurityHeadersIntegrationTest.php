<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Security;

use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;
use FlintPHP\Framework\Middleware\MiddlewareInterface;
use FlintPHP\Framework\Middleware\MiddlewareStack;
use FlintPHP\Framework\Security\Middleware\SecurityHeadersConfiguration;
use FlintPHP\Framework\Security\Middleware\SecurityHeadersMiddleware;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ErrorHandlingMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        try {
            return $next($request);
        } catch (RuntimeException $e) {
            return new Response('Internal Server Error', 500);
        }
    }
}

final class SecurityHeadersIntegrationTest extends TestCase
{
    #[Test]
    public function it_applies_security_headers_to_error_responses(): void
    {
        $config = new SecurityHeadersConfiguration();
        $securityHeaders = new SecurityHeadersMiddleware($config);
        $errorHandler = new ErrorHandlingMiddleware();

        $stack = new MiddlewareStack([
            $securityHeaders,
            $errorHandler
        ]);

        $request = new Request('GET', '/');

        // The terminal handler (e.g. controller) throws an exception
        $terminal = function (Request $req): Response {
            throw new RuntimeException('Something went wrong!');
        };

        // Execution unwinds: Controller throws -> ErrorHandler catches and returns 500 -> SecurityHeaders appends
        $response = $stack->handle($request, $terminal);

        $this->assertSame(500, $response->status());
        $this->assertSame('nosniff', $response->header('X-Content-Type-Options'));
        $this->assertSame('DENY', $response->header('X-Frame-Options'));
        $this->assertSame('strict-origin-when-cross-origin', $response->header('Referrer-Policy'));
    }
}
