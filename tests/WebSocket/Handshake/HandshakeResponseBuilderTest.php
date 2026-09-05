<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\WebSocket\Handshake;

use FlintPHP\Framework\Http\Method;
use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\WebSocket\Exception\HandshakeException;
use FlintPHP\Framework\WebSocket\Handshake\HandshakeResponseBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(HandshakeResponseBuilder::class)]
final class HandshakeResponseBuilderTest extends TestCase
{
    private HandshakeResponseBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new HandshakeResponseBuilder();
    }

    #[Test]
    public function it_builds_valid_response_from_rfc_example(): void
    {
        $request = new Request(
            method: 'GET',
            uri: '/chat',
            headers: [
                'Host' => 'server.example.com',
                'Upgrade' => 'websocket',
                'Connection' => 'Upgrade',
                'Sec-WebSocket-Key' => 'dGhlIHNhbXBsZSBub25jZQ==',
                'Origin' => 'http://example.com',
                'Sec-WebSocket-Protocol' => 'chat, superchat',
                'Sec-WebSocket-Version' => '13',
            ]
        );

        $response = $this->builder->build($request);

        $this->assertSame(101, $response->status());
        $this->assertSame('websocket', $response->header('Upgrade'));
        $this->assertSame('Upgrade', $response->header('Connection'));
        $this->assertSame('s3pPLMBiTxaQ9kYGzzhZRbK+xOo=', $response->header('Sec-WebSocket-Accept'));
        
        // Assert it does not leak client headers in the response
        $this->assertNull($response->header('Sec-WebSocket-Key'));
        $this->assertNull($response->header('Host'));
        
        // Assert request was left untouched
        $this->assertSame('dGhlIHNhbXBsZSBub25jZQ==', $request->header('Sec-WebSocket-Key'));
    }

    #[Test]
    public function it_generates_different_accepts_for_different_keys(): void
    {
        $request1 = new Request(
            method: 'GET',
            uri: '/chat',
            headers: [
                'Upgrade' => 'websocket',
                'Connection' => 'Upgrade',
                'Sec-WebSocket-Key' => base64_encode('1234567890123456'),
                'Sec-WebSocket-Version' => '13',
            ]
        );

        $request2 = new Request(
            method: 'GET',
            uri: '/chat',
            headers: [
                'Upgrade' => 'websocket',
                'Connection' => 'Upgrade',
                'Sec-WebSocket-Key' => base64_encode('abcdefghijklmnop'),
                'Sec-WebSocket-Version' => '13',
            ]
        );

        $response1 = $this->builder->build($request1);
        $response2 = $this->builder->build($request2);

        $this->assertNotSame($response1->header('Sec-WebSocket-Accept'), $response2->header('Sec-WebSocket-Accept'));
    }

    #[Test]
    public function it_throws_on_invalid_handshake(): void
    {
        $request = new Request(
            method: 'GET',
            uri: '/ws',
            headers: [
                'Upgrade' => 'websocket',
                // Missing Connection
                'Sec-WebSocket-Key' => 'dGhlIHNhbXBsZSBub25jZQ==',
                'Sec-WebSocket-Version' => '13',
            ]
        );

        $this->expectException(HandshakeException::class);
        $this->builder->build($request);
    }
}
