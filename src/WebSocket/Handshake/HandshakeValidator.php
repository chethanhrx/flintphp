<?php

declare(strict_types=1);

namespace FlintPHP\Framework\WebSocket\Handshake;

use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\WebSocket\Exception\HandshakeException;

/**
 * Validates an incoming HTTP request as a valid RFC 6455 WebSocket handshake.
 */
final class HandshakeValidator
{
    /**
     * Validate the handshake request.
     *
     * @throws HandshakeException If validation fails.
     */
    public function validate(Request $request): void
    {
        if ($request->method() !== 'GET') {
            throw new HandshakeException('WebSocket handshake requires GET method.');
        }

        $this->validateUpgradeHeader($request);
        $this->validateConnectionHeader($request);
        $this->validateVersionHeader($request);
        $this->validateKeyHeader($request);
    }

    private function validateUpgradeHeader(Request $request): void
    {
        $headers = $request->headers()->getAll('Upgrade');
        if (empty($headers)) {
            throw new HandshakeException('Missing Upgrade header.');
        }
        $found = false;
        foreach ($headers as $line) {
            $tokens = explode(',', $line);
            foreach ($tokens as $token) {
                $trimmed = trim($token);
                if ($trimmed === '') {
                    throw new HandshakeException('Invalid Upgrade header. Malformed token list.');
                }
                if (strcasecmp($trimmed, 'websocket') === 0) {
                    $found = true;
                }
            }
        }

        if (!$found) {
            throw new HandshakeException('Invalid Upgrade header. Must contain "websocket" token.');
        }
    }

    private function validateConnectionHeader(Request $request): void
    {
        $headers = $request->headers()->getAll('Connection');
        if (empty($headers)) {
            throw new HandshakeException('Missing Connection header.');
        }

        $found = false;
        foreach ($headers as $line) {
            $tokens = explode(',', $line);
            foreach ($tokens as $token) {
                $trimmed = trim($token);
                if ($trimmed === '') {
                    throw new HandshakeException('Invalid Connection header. Malformed token list.');
                }
                if (strcasecmp($trimmed, 'upgrade') === 0) {
                    $found = true;
                }
            }
        }

        if (!$found) {
            throw new HandshakeException('Invalid Connection header. Must contain "Upgrade" token.');
        }
    }

    private function validateVersionHeader(Request $request): void
    {
        $headers = $request->headers()->getAll('Sec-WebSocket-Version');
        if (count($headers) !== 1 || trim($headers[0]) !== '13') {
            throw new HandshakeException('Sec-WebSocket-Version must be exactly 13.');
        }
    }

    private function validateKeyHeader(Request $request): void
    {
        $headers = $request->headers()->getAll('Sec-WebSocket-Key');
        if (count($headers) !== 1) {
            throw new HandshakeException('Exactly one Sec-WebSocket-Key header is required.');
        }

        $key = $headers[0];
        if ($key === '') {
            throw new HandshakeException('Sec-WebSocket-Key cannot be empty.');
        }

        if (strlen($key) % 4 !== 0 || preg_match('/^[a-zA-Z0-9+\/]+={0,2}$/', $key) !== 1) {
            throw new HandshakeException('Sec-WebSocket-Key must be valid Base64.');
        }

        $decoded = base64_decode($key, true);
        if ($decoded === false || strlen($decoded) !== 16) {
            throw new HandshakeException('Sec-WebSocket-Key decoded length must be exactly 16 bytes.');
        }
    }
}
