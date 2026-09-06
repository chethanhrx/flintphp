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
}
