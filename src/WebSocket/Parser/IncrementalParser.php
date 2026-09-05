<?php

declare(strict_types=1);

namespace FlintPHP\Framework\WebSocket\Parser;

use FlintPHP\Framework\WebSocket\Exception\LimitExceededException;
use FlintPHP\Framework\WebSocket\Exception\ProtocolException;
use FlintPHP\Framework\WebSocket\Frame\Frame;
use FlintPHP\Framework\WebSocket\Frame\Opcode;

/**
 * An incremental RFC 6455 WebSocket frame parser designed for security
 * against untrusted network inputs.
 * 
 * Note on memory usage: Frame parsing may temporarily allocate additional memory
 * for payload extraction, mask expansion, and XOR output; the frame and parser 
 * limits bound this growth.
 */
final class IncrementalParser
{
    /**
     * Maximum unread wire data buffered by the parser.
     * Equals MAX_FRAME_PAYLOAD (2,097,152) + max frame overhead (14 bytes).
     */
    private const MAX_BUFFER = 2_097_166;
    private const MAX_FRAME_PAYLOAD = 2_097_152; // 2 MiB

    private const STATE_READ_HEADER = 0;
    private const STATE_READ_LENGTH_16 = 1;
    private const STATE_READ_LENGTH_64 = 2;
    private const STATE_READ_MASK = 3;
    private const STATE_READ_PAYLOAD = 4;
    private const STATE_ERROR = 5;

    private string $buffer = '';
    private int $offset = 0;
    private int $state = self::STATE_READ_HEADER;

    // Parsed frame state
    private bool $fin;
    private Opcode $opcode;
    private int $payloadLength;
    private string $maskingKey;

    /**
     * Push incoming network bytes and extract any complete frames.
     *
     * @param string $chunk
     * @return Frame[]
     *
     * @throws LimitExceededException
     * @throws ProtocolException
     */
    public function push(string $chunk): array
    {
        if ($this->state === self::STATE_ERROR) {
            throw new ProtocolException('Parser is in an error state and cannot accept more data.');
        }

        $chunkLen = strlen($chunk);
        if ($chunkLen === 0) {
            return [];
        }

        $bufferedBytes = strlen($this->buffer) - $this->offset;
        if ($bufferedBytes + $chunkLen > self::MAX_BUFFER) {
            $this->state = self::STATE_ERROR;
            throw new LimitExceededException('Parser buffer limit exceeded.');
        }

        $this->buffer .= $chunk;
        $frames = [];

        try {
            while (true) {
                $available = strlen($this->buffer) - $this->offset;

                if ($this->state === self::STATE_READ_HEADER) {
                    if ($available < 2) {
                        break;
                    }

                    $b0 = ord($this->buffer[$this->offset]);
                    $b1 = ord($this->buffer[$this->offset + 1]);

                    $this->fin = ($b0 & 0x80) !== 0;
                    $rsv1 = ($b0 & 0x40) !== 0;
                    $rsv2 = ($b0 & 0x20) !== 0;
                    $rsv3 = ($b0 & 0x10) !== 0;

                    if ($rsv1 || $rsv2 || $rsv3) {
                        throw new ProtocolException('RSV bits must not be set.');
                    }

                    $opcodeVal = $b0 & 0x0F;
                    $opcode = Opcode::tryFrom($opcodeVal);
                    if ($opcode === null) {
                        throw new ProtocolException(sprintf('Unknown opcode: %d', $opcodeVal));
                    }
                    $this->opcode = $opcode;

                    $masked = ($b1 & 0x80) !== 0;
                    if (!$masked) {
                        throw new ProtocolException('Client frames must be masked.');
                    }

                    $lenIndicator = $b1 & 0x7F;
                    $this->offset += 2;

                    if ($lenIndicator === 126) {
                        $this->state = self::STATE_READ_LENGTH_16;
                    } elseif ($lenIndicator === 127) {
                        $this->state = self::STATE_READ_LENGTH_64;
                    } else {
                        $this->payloadLength = $lenIndicator;
                        $this->validateFrameConstraints();
                        $this->state = self::STATE_READ_MASK;
                    }
                }

                if ($this->state === self::STATE_READ_LENGTH_16) {
                    $available = strlen($this->buffer) - $this->offset;
                    if ($available < 2) {
                        break;
                    }

                    $lengthBytes = substr($this->buffer, $this->offset, 2);
                    $this->payloadLength = unpack('n', $lengthBytes)[1];

                    if ($this->payloadLength < 126) {
                        throw new ProtocolException('Non-minimal payload length encoding (16-bit).');
                    }

                    $this->offset += 2;
                    $this->validateFrameConstraints();
                    $this->state = self::STATE_READ_MASK;
                }

                if ($this->state === self::STATE_READ_LENGTH_64) {
                    $available = strlen($this->buffer) - $this->offset;
                    if ($available < 8) {
                        break;
                    }

                    $lengthBytes = substr($this->buffer, $this->offset, 8);
                    
                    // Check if the most significant bit is set (must be 0 per RFC 6455)
                    if ((ord($lengthBytes[0]) & 0x80) !== 0) {
                        throw new ProtocolException('Payload length MSB must be 0.');
                    }

                    $this->payloadLength = unpack('J', $lengthBytes)[1];

                    if ($this->payloadLength <= 65535) {
                        throw new ProtocolException('Non-minimal payload length encoding (64-bit).');
                    }

                    $this->offset += 8;
                    $this->validateFrameConstraints();
                    $this->state = self::STATE_READ_MASK;
                }

                if ($this->state === self::STATE_READ_MASK) {
                    $available = strlen($this->buffer) - $this->offset;
                    if ($available < 4) {
                        break;
                    }

                    $this->maskingKey = substr($this->buffer, $this->offset, 4);
                    $this->offset += 4;
                    $this->state = self::STATE_READ_PAYLOAD;
                }

                if ($this->state === self::STATE_READ_PAYLOAD) {
                    $available = strlen($this->buffer) - $this->offset;
                    if ($available < $this->payloadLength) {
                        break;
                    }

                    $payload = '';
                    if ($this->payloadLength > 0) {
                        $maskedPayload = substr($this->buffer, $this->offset, $this->payloadLength);
                        
                        // Fast string XOR unmasking
                        $repeats = (int) ceil($this->payloadLength / 4);
                        $repeatedMask = str_repeat($this->maskingKey, $repeats);
                        
                        // Truncate to exact length to align perfectly with masked payload
                        $payload = $maskedPayload ^ substr($repeatedMask, 0, $this->payloadLength);
                    }

                    $this->offset += $this->payloadLength;

                    $frames[] = new Frame(
                        fin: $this->fin,
                        rsv1: false,
                        rsv2: false,
                        rsv3: false,
                        opcode: $this->opcode,
                        masked: true,
                        payload: $payload
                    );

                    $this->state = self::STATE_READ_HEADER;
                }
            }
        } catch (\Throwable $e) {
            $this->state = self::STATE_ERROR;
            throw $e;
        }

        // Clean up consumed bytes periodically or when complete to prevent memory explosion
        if ($this->offset > 0) {
            $this->buffer = substr($this->buffer, $this->offset);
            $this->offset = 0;
        }

        return $frames;
    }

    private function validateFrameConstraints(): void
    {
        if ($this->payloadLength > self::MAX_FRAME_PAYLOAD) {
            throw new LimitExceededException(
                sprintf('Frame payload size %d exceeds maximum of %d bytes.', $this->payloadLength, self::MAX_FRAME_PAYLOAD)
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

            if ($this->payloadLength > 125) {
                throw new ProtocolException('Control frame payload must not exceed 125 bytes.');
            }
        }

        if ($this->opcode === Opcode::CLOSE && $this->payloadLength === 1) {
            throw new ProtocolException('Close frame payload length cannot be exactly 1 byte.');
        }
    }
}
