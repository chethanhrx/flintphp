<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\WebSocket;

use FlintPHP\Framework\WebSocket\Exception\HandshakeException;
use FlintPHP\Framework\WebSocket\Exception\InvalidUtf8Exception;
use FlintPHP\Framework\WebSocket\Exception\LimitExceededException;
use FlintPHP\Framework\WebSocket\Exception\ProtocolException;
use FlintPHP\Framework\WebSocket\Exception\WebSocketException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExceptionTest extends TestCase
{
    #[Test]
    public function it_verifies_websocket_exception_is_runtime_exception(): void
    {
        $exception = new WebSocketException('Base error');
        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertSame('Base error', $exception->getMessage());
    }

    #[Test]
    public function it_verifies_hierarchy_and_previous_throwable(): void
    {
        $previous = new \Exception('Root cause');
        
        $exceptions = [
            new HandshakeException('Handshake failed', 1, $previous),
            new ProtocolException('Protocol violation', 2, $previous),
            new LimitExceededException('Limit reached', 3, $previous),
            new InvalidUtf8Exception('Bad encoding', 4, $previous),
        ];

        foreach ($exceptions as $e) {
            $this->assertInstanceOf(WebSocketException::class, $e);
            $this->assertSame($previous, $e->getPrevious());
            $this->assertNotEmpty($e->getMessage());
        }
    }
}
