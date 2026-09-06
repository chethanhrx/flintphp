<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Http\Exception;

use FlintPHP\Framework\Http\Exception\ExceptionHandler;
use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ExceptionHandler::class)]
final class ExceptionHandlerTest extends TestCase
{
    #[Test]
    public function it_returns_a_generic_500_response(): void
    {
        $handler = new ExceptionHandler();

        $exception = new RuntimeException('/home/chethan/secret/database-password');
        $request = new Request('GET', '/test');

        $response = $handler->handle($exception, $request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(500, $response->status());
        $this->assertSame("Internal Server Error\n", $response->body());

        // Security assertion: message not exposed
        $this->assertStringNotContainsString('/home/chethan/secret', $response->body());
        $this->assertStringNotContainsString('RuntimeException', $response->body());
    }
    #[Test]
    public function it_returns_http_exception_status_and_message(): void
    {
        $handler = new ExceptionHandler();

        $exception = new \FlintPHP\Framework\Http\Exception\HttpException(404, 'User not found');
        $request = new Request('GET', '/test');

        $response = $handler->handle($exception, $request);

        $this->assertSame(404, $response->status());
        $this->assertSame("User not found\n", $response->body());
    }

    #[Test]
    public function it_returns_generic_message_if_http_exception_message_is_empty(): void
    {
        $handler = new ExceptionHandler();

        $exception = new \FlintPHP\Framework\Http\Exception\HttpException(401);
        $request = new Request('GET', '/test');

        $response = $handler->handle($exception, $request);

        $this->assertSame(401, $response->status());
        $this->assertSame("HTTP 401\n", $response->body());
    }

    #[Test]
    public function it_does_not_expose_previous_exception_details(): void
    {
        $handler = new ExceptionHandler();

        $previous = new RuntimeException('Secret database error');
        $exception = new \FlintPHP\Framework\Http\Exception\HttpException(500, 'Public Error', $previous);
        $request = new Request('GET', '/test');

        $response = $handler->handle($exception, $request);

        $this->assertSame(500, $response->status());
        $this->assertSame("Public Error\n", $response->body());
        $this->assertStringNotContainsString('Secret database error', $response->body());
    }
}
