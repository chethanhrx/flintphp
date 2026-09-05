<?php

declare(strict_types=1);

namespace FlintPHP\Framework\WebSocket\Frame;

use FlintPHP\Framework\WebSocket\Exception\LimitExceededException;
use FlintPHP\Framework\WebSocket\Exception\ProtocolException;

/**
 * Builds WebSocket frames for server-to-client transmission.
 */
final class FrameBuilder
{
    private const MAX_FRAME_PAYLOAD = 2_097_152; // 2 MiB

    /**
     * Build an unmasked WebSocket frame representing server output.
     *
     * @throws ProtocolException If the opcode/payload combination violates RFC 6455.
     * @throws LimitExceededException If the payload exceeds the maximum frame size.
     */
    public function buildServerFrame(
        Opcode $opcode,
        string $payload,
        bool $fin = true
    ): string {
        $length = strlen($payload);

        if ($length > self::MAX_FRAME_PAYLOAD) {
            throw new LimitExceededException(
                sprintf('Frame payload exceeds maximum allowed size of %d bytes.', self::MAX_FRAME_PAYLOAD)
            );
        }

        $isControlFrame = match ($opcode) {
            Opcode::CLOSE, Opcode::PING, Opcode::PONG => true,
            default => false,
        };

        if ($isControlFrame) {
            if (!$fin) {
                throw new ProtocolException('Control frames must not be fragmented (FIN must be true).');
            }

            if ($length > 125) {
                throw new ProtocolException('Control frame payload must not exceed 125 bytes.');
            }
        }

        if ($opcode === Opcode::CLOSE && $length === 1) {
            throw new ProtocolException('Close frame payload length cannot be exactly 1 byte.');
        }

        // First byte: FIN (1 bit) | RSV1,2,3 (0) | Opcode (4 bits)
        $firstByte = ($fin ? 0x80 : 0x00) | $opcode->value;

        // Second byte: MASK (1 bit, always 0 for server) | Payload len (7 bits)
        if ($length <= 125) {
            $header = pack('CC', $firstByte, $length);
        } elseif ($length <= 65535) {
            $header = pack('CCn', $firstByte, 126, $length);
        } else {
            // 'J' format is unsigned 64-bit big endian (added in PHP 5.6.3).
            // It is safe because MAX_FRAME_PAYLOAD prevents lengths approaching the 63-bit signed max.
            $header = pack('CCJ', $firstByte, 127, $length);
        }

        return $header . $payload;
    }
}
