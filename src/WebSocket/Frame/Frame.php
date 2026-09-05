<?php

declare(strict_types=1);

namespace FlintPHP\Framework\WebSocket\Frame;

use FlintPHP\Framework\WebSocket\Exception\LimitExceededException;
use FlintPHP\Framework\WebSocket\Exception\ProtocolException;

/**
 * Represents a decoded, unmasked WebSocket frame.
 */
final readonly class Frame
{
    private const MAX_FRAME_PAYLOAD = 2_097_152; // 2 MiB

    public function __construct(
        public bool $fin,
        public bool $rsv1,
        public bool $rsv2,
        public bool $rsv3,
        public Opcode $opcode,
        public bool $masked,
        public string $payload,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->rsv1 || $this->rsv2 || $this->rsv3) {
            throw new ProtocolException('RSV bits must not be set (extensions are not supported).');
        }

        $length = strlen($this->payload);
        if ($length > self::MAX_FRAME_PAYLOAD) {
            throw new LimitExceededException(
                sprintf('Frame payload exceeds maximum allowed size of %d bytes.', self::MAX_FRAME_PAYLOAD)
            );
        }

        $isControlFrame = match ($this->opcode) {
            Opcode::CLOSE, Opcode::PING, Opcode::PONG => true,
            default => false,
        };

        if ($isControlFrame) {
            if (!$this->fin) {
                throw new ProtocolException('Control frames must not be fragmented (FIN must be true).');
            }

            if ($length > 125) {
                throw new ProtocolException('Control frame payload must not exceed 125 bytes.');
            }
        }

        if ($this->opcode === Opcode::CLOSE && $length === 1) {
            throw new ProtocolException('Close frame payload length cannot be exactly 1 byte.');
        }
    }
}
