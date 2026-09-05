<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\WebSocket\Handshake;

use FlintPHP\Framework\Http\HeaderBag;
use FlintPHP\Framework\Http\Method;
use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\WebSocket\Exception\HandshakeException;
use FlintPHP\Framework\WebSocket\Handshake\HandshakeValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(HandshakeValidator::class)]
final class HandshakeValidatorTest extends TestCase
{
    private HandshakeValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new HandshakeValidator();
    }

    private function createValidRequest(): Request
    {
        return new Request(
            method: 'GET',
            uri: '/ws',
            headers: [
                'Upgrade' => 'websocket',
                'Connection' => 'Upgrade',
                'Sec-WebSocket-Version' => '13',
                'Sec-WebSocket-Key' => 'dGhlIHNhbXBsZSBub25jZQ==',
            ]
        );
    }

    #[Test]
    public function it_accepts_a_completely_valid_handshake(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate($this->createValidRequest());
    }

    #[Test]
    public function it_rejects_non_get_methods(): void
    {
        $methods = [Method::POST, Method::PUT, Method::DELETE, Method::HEAD, Method::OPTIONS, Method::PATCH];

        foreach ($methods as $method) {
            $request = new Request(
                method: $method->value,
                uri: '/ws',
                headers: $this->createValidRequest()->headers()->all()
            );

            try {
                $this->validator->validate($request);
                $this->fail("Expected HandshakeException for method " . $method->value);
            } catch (HandshakeException $e) {
                $this->assertStringContainsString('requires GET method', $e->getMessage());
            }
        }
    }

    #[Test]
    public function it_accepts_valid_upgrade_headers(): void
    {
        $valid = ['websocket', 'WebSocket', 'WEBSOCKET'];
        
        foreach ($valid as $value) {
            $request = $this->createValidRequest()->withHeader('Upgrade', $value);
            $this->validator->validate($request);
            $this->assertTrue(true); // Reached without exception
        }
    }

    #[Test]
    public function it_rejects_invalid_upgrade_headers(): void
    {
        $invalid = [
            'not-websocket',
            'websocket-malicious',
            'mywebsocket',
            '',
        ];

        foreach ($invalid as $value) {
            $request = $this->createValidRequest()->withHeader('Upgrade', $value);
            try {
                $this->validator->validate($request);
                $this->fail("Expected exception for Upgrade: $value");
            } catch (HandshakeException) {
                $this->assertTrue(true);
            }
        }

        // Missing Upgrade
        $request = new Request(method: 'GET', uri: '/ws', headers: [
            'Connection' => 'Upgrade',
            'Sec-WebSocket-Version' => '13',
            'Sec-WebSocket-Key' => 'dGhlIHNhbXBsZSBub25jZQ==',
        ]);
        $this->expectException(HandshakeException::class);
        $this->expectExceptionMessage('Missing Upgrade header');
        $this->validator->validate($request);
    }

    #[Test]
    public function it_accepts_valid_connection_headers(): void
    {
        $valid = [
            'Upgrade',
            'keep-alive, Upgrade',
            'Keep-Alive, upgrade',
            'upgrade, keep-alive',
            '  upgrade  ,  close  '
        ];
        
        foreach ($valid as $value) {
            $request = $this->createValidRequest()->withHeader('Connection', $value);
            $this->validator->validate($request);
            $this->assertTrue(true); // Reached without exception
        }
    }

    #[Test]
    public function it_rejects_invalid_connection_headers(): void
    {
        $invalid = [
            'not-upgrade',
            'myupgrade',
            'upgrade-malicious',
            'keep-alive',
            ',',
            'Upgrade,',
            ',Upgrade',
            '',
        ];

        foreach ($invalid as $value) {
            try {
                $request = $this->createValidRequest()->withHeader('Connection', $value);
                $this->validator->validate($request);
                $this->fail("Expected exception for Connection: $value");
            } catch (HandshakeException) {
                $this->assertTrue(true);
            }
        }

        // Missing Connection
        $request = new Request(method: 'GET', uri: '/ws', headers: [
            'Upgrade' => 'websocket',
            'Sec-WebSocket-Version' => '13',
            'Sec-WebSocket-Key' => 'dGhlIHNhbXBsZSBub25jZQ==',
        ]);
        $this->expectException(HandshakeException::class);
        $this->expectExceptionMessage('Missing Connection header');
        $this->validator->validate($request);
    }

    #[Test]
    public function it_validates_version_header_strictly(): void
    {
        $invalid = ['12', '14', '13abc', '013', ''];

        foreach ($invalid as $value) {
            $request = $this->createValidRequest()->withHeader('Sec-WebSocket-Version', $value);
            try {
                $this->validator->validate($request);
                $this->fail("Expected exception for Version: $value");
            } catch (HandshakeException) {
                $this->assertTrue(true);
            }
        }

        // Multiple versions should be rejected
        $request = new Request(method: 'GET', uri: '/ws', headers: [
            'Upgrade' => 'websocket',
            'Connection' => 'Upgrade',
            'Sec-WebSocket-Version' => ['13', '14'],
            'Sec-WebSocket-Key' => 'dGhlIHNhbXBsZSBub25jZQ==',
        ]);
        try {
            $this->validator->validate($request);
            $this->fail("Expected exception for multiple Version headers");
        } catch (HandshakeException) {
        }
    }

    #[Test]
    public function it_validates_key_header_strictly(): void
    {
        // Valid alternative keys
        $validKeys = [
            'dGhlIHNhbXBsZSBub25jZQ==', // RFC Example
            base64_encode('1234567890123456'), // alphanumeric
            base64_encode(random_bytes(16)), // random binary
        ];

        foreach ($validKeys as $validKey) {
            $request = $this->createValidRequest()->withHeader('Sec-WebSocket-Key', $validKey);
            $this->validator->validate($request);
            $this->assertTrue(true);
        }

        // Missing key
        $request = new Request(method: 'GET', uri: '/ws', headers: [
            'Upgrade' => 'websocket',
            'Connection' => 'Upgrade',
            'Sec-WebSocket-Version' => '13',
        ]);
        try {
            $this->validator->validate($request);
            $this->fail("Expected exception for missing Key");
        } catch (HandshakeException) {
        }

        // Multiple keys should be rejected
        $request = new Request(method: 'GET', uri: '/ws', headers: [
            'Upgrade' => 'websocket',
            'Connection' => 'Upgrade',
            'Sec-WebSocket-Version' => '13',
            'Sec-WebSocket-Key' => ['dGhlIHNhbXBsZSBub25jZQ==', 'dGhlIHNhbXBsZSBub25jZQ=='],
        ]);
        try {
            $this->validator->validate($request);
            $this->fail("Expected exception for multiple Key headers");
        } catch (HandshakeException) {
        }

        // Malformed/Invalid keys
        $invalid = [
            '', // empty
            'dGhlIHNhbXBsZSBub25jZQ', // missing padding (actually base64_decode handles it, but our regex requires valid padding structure)
            'dGhlIHNhbXBsZSBub25jZQ====', // too much padding
            'not-base64!', // invalid base64 chars
            'dGhlIHNhbXBs', // too short (not 16 bytes decoded)
            base64_encode('123456789012345'), // 15 bytes
            base64_encode('12345678901234567'), // 17 bytes
            "dGhlIHNh\r\nbXBsZSBub25jZQ==", // CRLF injection attempt
        ];

        foreach ($invalid as $value) {
            try {
                $request = $this->createValidRequest()->withHeader('Sec-WebSocket-Key', $value);
                $this->validator->validate($request);
                $this->fail("Expected exception for Key: $value");
            } catch (\InvalidArgumentException $e) {
                $this->assertTrue(true);
            } catch (HandshakeException $e) {
                $this->assertTrue(true);
            }
        }
    }
}
