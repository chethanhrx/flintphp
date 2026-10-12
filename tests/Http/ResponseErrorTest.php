<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Http;

use FlintPHP\Framework\Http\Exception\HttpException;
use FlintPHP\Framework\Http\Exception\JsonExceptionHandler;
use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ResponseErrorTest extends TestCase
{
    public function testErrorShape(): void
    {
        $response = Response::error('Not found', 404, 'not_found');

        $this->assertSame(404, $response->status());
        $this->assertSame('application/json', $response->header('Content-Type'));

        $payload = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('Not found', $payload['error']['message']);
        $this->assertSame(404, $payload['error']['status']);
        $this->assertSame('not_found', $payload['error']['code']);
    }

    public function testJsonExceptionHandlerHttpException(): void
    {
        $handler = new JsonExceptionHandler();
        $request = new Request('GET', '/x', [], '', [], [], [], [], null);
        $response = $handler->handle(new HttpException(422, 'Invalid'), $request);

        $this->assertSame(422, $response->status());
        $payload = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('Invalid', $payload['error']['message']);
    }

    public function testJsonExceptionHandlerHidesInternalErrors(): void
    {
        $handler = new JsonExceptionHandler();
        $request = new Request('GET', '/x', [], '', [], [], [], [], null);
        $response = $handler->handle(new RuntimeException('secret path /etc/passwd'), $request);

        $this->assertSame(500, $response->status());
        $this->assertStringNotContainsString('passwd', $response->body());
        $payload = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('Internal Server Error', $payload['error']['message']);
    }
}
