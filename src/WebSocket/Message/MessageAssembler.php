<?php

declare(strict_types=1);

namespace FlintPHP\Framework\WebSocket\Message;

use FlintPHP\Framework\WebSocket\Exception\InvalidUtf8Exception;
use FlintPHP\Framework\WebSocket\Exception\LimitExceededException;
use FlintPHP\Framework\WebSocket\Exception\ProtocolException;
use FlintPHP\Framework\WebSocket\Frame\Frame;
use FlintPHP\Framework\WebSocket\Frame\Opcode;

/**
 * Assembles WebSocket frames into complete application messages.
 */
final class MessageAssembler
{
    private const MAX_MESSAGE_SIZE = 10_485_760; // 10 MiB
    private const MAX_FRAGMENTS = 1000;

    private bool $isFragmented = false;
    private int $fragmentCount = 0;
    private int $accumulatedSize = 0;
    private string $payloadBuffer = '';
    private bool $isText = false;
    private bool $isError = false;

    /**
     * Push a parsed frame into the assembler.
     * Returns a fully assembled Message if one is completed, otherwise null.
     *
     * @param Frame $frame
     * @return Message|null
     * 
     * @throws ProtocolException If the fragmentation sequence violates RFC 6455.
     * @throws LimitExceededException If the message exceeds 10 MiB or 1000 fragments.
     * @throws InvalidUtf8Exception If a complete TEXT message is invalid UTF-8.
     */
    public function push(Frame $frame): ?Message
    {
        if ($this->isError) {
            throw new ProtocolException('Assembler is in an error state and cannot accept more frames.');
        }

        $opcode = $frame->opcode;

        // Control frames are ignored and do not alter fragmentation state.
        if ($opcode === Opcode::PING || $opcode === Opcode::PONG || $opcode === Opcode::CLOSE) {
            return null;
        }

        try {
            // Unfragmented Data Frame
            if (!$this->isFragmented && $frame->fin && ($opcode === Opcode::TEXT || $opcode === Opcode::BINARY)) {
                $isText = ($opcode === Opcode::TEXT);
                $this->validateLimits(strlen($frame->payload), 1);
                $this->validateUtf8($frame->payload, $isText);
                return new Message($frame->payload, $isText);
            }

            // Beginning of Fragmentation
            if (!$this->isFragmented && !$frame->fin && ($opcode === Opcode::TEXT || $opcode === Opcode::BINARY)) {
                $this->isFragmented = true;
                $this->isText = ($opcode === Opcode::TEXT);
                $this->fragmentCount = 1;
                $this->accumulatedSize = strlen($frame->payload);
                $this->validateLimits($this->accumulatedSize, $this->fragmentCount);
                $this->payloadBuffer = $frame->payload;
                return null;
            }

            // Continuation Frame
            if ($this->isFragmented && $opcode === Opcode::CONTINUATION) {
                $chunkSize = strlen($frame->payload);
                $this->validateLimits($this->accumulatedSize + $chunkSize, $this->fragmentCount + 1);
                
                $this->payloadBuffer .= $frame->payload;
                $this->accumulatedSize += $chunkSize;
                $this->fragmentCount++;

                if ($frame->fin) {
                    $completePayload = $this->payloadBuffer;
                    $isText = $this->isText;
                    
                    // Reset state before validation in case validation fails, keeping machine safe
                    // Wait, if validation fails, it goes to catch which sets isError anyway.
                    // But good practice to reset first.
                    $this->resetState();
                    
                    $this->validateUtf8($completePayload, $isText);

                    return new Message($completePayload, $isText);
                }

                return null;
            }

            // Invalid States
            if (!$this->isFragmented && $opcode === Opcode::CONTINUATION) {
                throw new ProtocolException('CONTINUATION frame received without an active fragmented message.');
            }

            if ($this->isFragmented && ($opcode === Opcode::TEXT || $opcode === Opcode::BINARY)) {
                throw new ProtocolException('TEXT or BINARY frame received during an active fragmented message.');
            }

        } catch (\Throwable $e) {
            $this->isError = true;
            $this->resetState();
            throw $e;
        }

        // Should be unreachable if Frame validated its opcode properly, but safety fallback.
        throw new ProtocolException('Unexpected frame state encountered.');
    }

    private function validateLimits(int $size, int $count): void
    {
        if ($size > self::MAX_MESSAGE_SIZE) {
            throw new LimitExceededException('Message size exceeds 10 MiB limit.');
        }

        if ($count > self::MAX_FRAGMENTS) {
            throw new LimitExceededException('Message fragment count exceeds limit of 1000.');
        }
    }

    private function validateUtf8(string $payload, bool $isText): void
    {
        if (!$isText || $payload === '') {
            return;
        }

        // preg_match returns 1 if it matches, 0 if it doesn't, and false on error
        if (preg_match('//u', $payload) !== 1) {
            throw new InvalidUtf8Exception('TEXT message contains invalid UTF-8.');
        }
    }

    private function resetState(): void
    {
        $this->isFragmented = false;
        $this->fragmentCount = 0;
        $this->accumulatedSize = 0;
        $this->payloadBuffer = '';
        $this->isText = false;
    }
}
