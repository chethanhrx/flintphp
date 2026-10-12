<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Foundation;

use FlintPHP\Framework\Foundation\Application;
use FlintPHP\Framework\Http\Exception\ExceptionHandlerInterface;
use FlintPHP\Framework\Http\Exception\HttpException;
use FlintPHP\Framework\Http\Exception\JsonExceptionHandler;
use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ExceptionHandlerReplacementTest extends TestCase
{
    public function testSetExceptionHandlerBeforeKernel(): void
    {
        $app = new Application('/tmp/flint-app');
        $handler = new JsonExceptionHandler();

        $app->setExceptionHandler($handler);

        $this->assertSame($handler, $app->exceptionHandler());
        $this->assertSame(
            $handler,
            $app->container()->get(ExceptionHandlerInterface::class)
        );

        $app->router()->get('/boom', static function (): Response {
            throw new HttpException(422, 'Invalid payload');
        });

        $response = $app->kernel()->handle(new Request('GET', '/boom', [], '', [], [], [], [], null));

        $this->assertSame(422, $response->status());
        $this->assertSame('application/json', $response->header('Content-Type'));
        $payload = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('Invalid payload', $payload['error']['message']);
    }

    public function testSetExceptionHandlerAfterKernelThrows(): void
    {
        $app = new Application('/tmp/flint-app');
        $app->kernel();

        $this->expectException(LogicException::class);
        $app->setExceptionHandler(new JsonExceptionHandler());
    }

    public function testJsonHandlerHidesInternalExceptions(): void
    {
        $app = new Application('/tmp/flint-app');
        $app->setExceptionHandler(new JsonExceptionHandler());

        $app->router()->get('/secret', static function (): Response {
            throw new RuntimeException('secret /etc/passwd');
        });

        $response = $app->kernel()->handle(new Request('GET', '/secret', [], '', [], [], [], [], null));

        $this->assertSame(500, $response->status());
        $this->assertStringNotContainsString('passwd', $response->body());
    }
}
