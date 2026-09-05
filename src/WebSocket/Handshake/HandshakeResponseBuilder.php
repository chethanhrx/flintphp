<?php

declare(strict_types=1);

namespace FlintPHP\Framework\WebSocket\Handshake;

use FlintPHP\Framework\Http\HeaderBag;
use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;

/**
 * Builds a 101 Switching Protocols response for a valid WebSocket handshake.
 */
final class HandshakeResponseBuilder
{
    private const WEBSOCKET_GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

    public function __construct(
        private readonly HandshakeValidator $validator = new HandshakeValidator(),
    ) {}

    /**
     * Build the WebSocket response.
     *
     * Validates the request and generates the corresponding Sec-WebSocket-Accept hash.
     *
     * @throws \FlintPHP\Framework\WebSocket\Exception\HandshakeException
     */
    public function build(Request $request): Response
    {
        $this->validator->validate($request);

        // Validation guarantees exactly one header exists and it is valid Base64
        $key = $request->header('Sec-WebSocket-Key');

        $accept = base64_encode(sha1($key . self::WEBSOCKET_GUID, true));

        return new Response(
            body: '',
            status: 101,
            headers: new HeaderBag([
                'Upgrade' => 'websocket',
                'Connection' => 'Upgrade',
                'Sec-WebSocket-Accept' => $accept,
            ])
        );
    }
}
