<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\WebSocket\Parser;

use FlintPHP\Framework\WebSocket\Exception\LimitExceededException;
use FlintPHP\Framework\WebSocket\Exception\ProtocolException;
use FlintPHP\Framework\WebSocket\Frame\Frame;
use FlintPHP\Framework\WebSocket\Frame\Opcode;
use FlintPHP\Framework\WebSocket\Parser\IncrementalParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IncrementalParser::class)]
final class IncrementalParserTest extends TestCase
{
    private IncrementalParser $parser;

    protected function setUp(): void
    {
        $this->parser = new IncrementalParser();
    }

    private function createMaskedFrame(
        Opcode $opcode,
        string $payload,
        bool $fin = true,
        string $mask = '1234'
    ): string {
        $firstByte = ($fin ? 0x80 : 0x00) | $opcode->value;
        $length = strlen($payload);

        if ($length <= 125) {
            $header = pack('CC', $firstByte, 0x80 | $length);
        } elseif ($length <= 65535) {
            $header = pack('CCn', $firstByte, 0x80 | 126, $length);
        } else {
            $header = pack('CCJ', $firstByte, 0x80 | 127, $length);
        }

        $header .= $mask;
        
        $repeats = (int) ceil($length / 4);
        $repeatedMask = str_repeat($mask, $repeats);
        $maskedPayload = $payload ^ substr($repeatedMask, 0, $length);

        return $header . $maskedPayload;
    }

    #[Test]
    public function it_parses_single_complete_frame(): void
    {
        $wire = $this->createMaskedFrame(Opcode::TEXT, 'hello', true, "\x37\x7A\x21\x4C");
        
        $frames = $this->parser->push($wire);
        
        $this->assertCount(1, $frames);
        $frame = $frames[0];
        $this->assertTrue($frame->fin);
        $this->assertSame(Opcode::TEXT, $frame->opcode);
        $this->assertTrue($frame->masked);
        $this->assertSame('hello', $frame->payload);
        $this->assertFalse($frame->rsv1);
    }

    #[Test]
    public function it_parses_multiple_frames_in_one_push(): void
    {
        $wire = $this->createMaskedFrame(Opcode::TEXT, 'part1', false)
              . $this->createMaskedFrame(Opcode::PING, 'ping', true)
              . $this->createMaskedFrame(Opcode::CONTINUATION, 'part2', true);

        $frames = $this->parser->push($wire);

        $this->assertCount(3, $frames);
        
        $this->assertFalse($frames[0]->fin);
        $this->assertSame(Opcode::TEXT, $frames[0]->opcode);
        $this->assertSame('part1', $frames[0]->payload);

        $this->assertTrue($frames[1]->fin);
        $this->assertSame(Opcode::PING, $frames[1]->opcode);
        $this->assertSame('ping', $frames[1]->payload);

        $this->assertTrue($frames[2]->fin);
        $this->assertSame(Opcode::CONTINUATION, $frames[2]->opcode);
        $this->assertSame('part2', $frames[2]->payload);
    }

    #[Test]
    public function it_returns_empty_when_pushing_empty_string(): void
    {
        $this->assertSame([], $this->parser->push(''));
        $this->assertSame([], $this->parser->push(''));
    }

    #[Test]
    public function it_buffers_incomplete_frames_until_complete(): void
    {
        $wire = $this->createMaskedFrame(Opcode::TEXT, 'hello world', true);
        
        $this->assertSame([], $this->parser->push(substr($wire, 0, 1))); // First byte
        $this->assertSame([], $this->parser->push(substr($wire, 1, 1))); // Second byte
        $this->assertSame([], $this->parser->push(substr($wire, 2, 2))); // Part of mask
        $this->assertSame([], $this->parser->push(substr($wire, 4, 3))); // Rest of mask + 1 byte payload
        $this->assertSame([], $this->parser->push(substr($wire, 7, 5))); // More payload
        
        // Final byte
        $frames = $this->parser->push(substr($wire, 12));
        
        $this->assertCount(1, $frames);
        $this->assertSame('hello world', $frames[0]->payload);
    }

    #[Test]
    public function it_supports_arbitrary_binary_payloads_with_deterministic_masks(): void
    {
        $payload = pack('C*', 0x00, 0x01, 0x7F, 0x80, 0xFE, 0xFF);
        $masks = [
            pack('C*', 0x00, 0x00, 0x00, 0x00),
            pack('C*', 0xFF, 0xFF, 0xFF, 0xFF),
            pack('C*', 0x00, 0xFF, 0x00, 0xFF),
            pack('C*', 0x12, 0x34, 0x56, 0x78),
        ];

        foreach ($masks as $mask) {
            $parser = new IncrementalParser();
            $wire = $this->createMaskedFrame(Opcode::BINARY, $payload, true, $mask);
            $frames = $parser->push($wire);
            $this->assertCount(1, $frames);
            $this->assertSame($payload, $frames[0]->payload);
        }
    }

    #[Test]
    public function it_rejects_unmasked_frames(): void
    {
        // MSB 0 means unmasked
        $wire = pack('CC', 0x81, 0x05) . 'hello';
        
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Client frames must be masked');
        
        $this->parser->push($wire);
    }

    #[Test]
    public function it_rejects_rsv_bits(): void
    {
        $invalidFirstBytes = [
            0x81 | 0x40, // RSV1
            0x81 | 0x20, // RSV2
            0x81 | 0x10, // RSV3
            0x81 | 0x70, // All RSV
        ];

        foreach ($invalidFirstBytes as $b0) {
            $parser = new IncrementalParser();
            $wire = pack('CC', $b0, 0x80 | 0x00) . '1234';
            try {
                $parser->push($wire);
                $this->fail('Expected ProtocolException for RSV bits');
            } catch (ProtocolException) {
                $this->assertTrue(true);
            }
        }
    }

    #[Test]
    public function it_rejects_unknown_opcodes(): void
    {
        $invalidOpcodes = [3, 4, 5, 6, 7, 11, 12, 13, 14, 15];

        foreach ($invalidOpcodes as $opcode) {
            $parser = new IncrementalParser();
            $wire = pack('CC', 0x80 | $opcode, 0x80 | 0x00) . '1234';
            try {
                $parser->push($wire);
                $this->fail('Expected ProtocolException for opcode ' . $opcode);
            } catch (ProtocolException) {
                $this->assertTrue(true);
            }
        }
    }

    #[Test]
    public function it_rejects_non_minimal_length_16(): void
    {
        // Encode 125 using 126 indicator
        $wire = pack('CCn', 0x81, 0x80 | 126, 125) . '1234' . str_repeat('a', 125);
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Non-minimal payload length encoding');
        $this->parser->push($wire);
    }

    #[Test]
    public function it_rejects_non_minimal_length_64(): void
    {
        // Encode 65535 using 127 indicator
        $wire = pack('CCJ', 0x81, 0x80 | 127, 65535) . '1234' . str_repeat('a', 65535);
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Non-minimal payload length encoding');
        $this->parser->push($wire);
    }

    #[Test]
    public function it_rejects_64bit_length_with_msb_set(): void
    {
        $wire = pack('CC', 0x81, 0x80 | 127) . pack('H*', '8000000000000000');
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Payload length MSB must be 0');
        $this->parser->push($wire);
    }

    #[Test]
    public function it_rejects_frame_payload_above_limit_early(): void
    {
        $limit = 2_097_152;
        $oversized = $limit + 1;
        // The parser should reject as soon as it parses the length,
        // without waiting for the payload or mask!
        $wire = pack('CCJ', 0x81, 0x80 | 127, $oversized);
        
        $this->expectException(LimitExceededException::class);
        $this->expectExceptionMessage('Frame payload size 2097153 exceeds maximum');
        
        $this->parser->push($wire);
    }

    #[Test]
    public function it_rejects_control_frame_fragmentation(): void
    {
        $wire = $this->createMaskedFrame(Opcode::PING, 'ping', false);
        $this->expectException(ProtocolException::class);
        $this->parser->push($wire);
    }

    #[Test]
    public function it_rejects_control_frame_oversized(): void
    {
        $wire = $this->createMaskedFrame(Opcode::PING, str_repeat('a', 126), true);
        $this->expectException(ProtocolException::class);
        $this->parser->push($wire);
    }

    #[Test]
    public function it_rejects_close_frame_length_1(): void
    {
        $wire = $this->createMaskedFrame(Opcode::CLOSE, 'a', true);
        $this->expectException(ProtocolException::class);
        $this->parser->push($wire);
    }

    #[Test]
    public function it_enforces_buffer_limit(): void
    {
        // Pushing a chunk that makes buffer > MAX_BUFFER (2,097,166)
        $chunk = str_repeat('x', 2_097_167);
        
        $this->expectException(LimitExceededException::class);
        $this->expectExceptionMessage('Parser buffer limit exceeded');
        
        $this->parser->push($chunk);
    }

    #[Test]
    public function it_remains_in_error_state_after_exception(): void
    {
        $wire = pack('CC', 0x81 | 0x40, 0x80 | 0) . '1234'; // RSV1 set
        
        try {
            $this->parser->push($wire);
            $this->fail('Expected exception');
        } catch (ProtocolException) {
        }

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Parser is in an error state');
        $this->parser->push('more data');
    }

    #[Test]
    public function it_parses_length_65536(): void
    {
        $payload = str_repeat('x', 65536);
        $wire = $this->createMaskedFrame(Opcode::TEXT, $payload, true);
        
        // Push in two parts to test internal buffering of large payload
        $this->assertSame([], $this->parser->push(substr($wire, 0, 100)));
        $frames = $this->parser->push(substr($wire, 100));
        
        $this->assertCount(1, $frames);
        $this->assertSame(65536, strlen($frames[0]->payload));
    }

    #[Test]
    public function it_successfully_parses_maximum_size_frame_in_one_push(): void
    {
        $payload = str_repeat('m', 2_097_152); // 2 MiB exactly
        $wire = $this->createMaskedFrame(Opcode::TEXT, $payload, true, "\x11\x22\x33\x44");

        // The entire wire size should be exactly 2,097,166 bytes
        $this->assertSame(2_097_166, strlen($wire));

        $frames = $this->parser->push($wire);

        $this->assertCount(1, $frames);
        $frame = $frames[0];
        
        $this->assertTrue($frame->fin);
        $this->assertSame(Opcode::TEXT, $frame->opcode);
        $this->assertTrue($frame->masked);
        $this->assertSame(2_097_152, strlen($frame->payload));
        $this->assertSame($payload, $frame->payload); // Byte-for-byte identical
    }

    #[Test]
    public function it_successfully_parses_maximum_size_frame_incrementally(): void
    {
        $payload = str_repeat('i', 2_097_152);
        $wire = $this->createMaskedFrame(Opcode::BINARY, $payload, true, "\xAA\xBB\xCC\xDD");

        $this->assertSame([], $this->parser->push(substr($wire, 0, 2))); // Base header
        $this->assertSame([], $this->parser->push(substr($wire, 2, 8))); // 64-bit length
        $this->assertSame([], $this->parser->push(substr($wire, 10, 4))); // Mask
        
        // Push payload in chunks
        $this->assertSame([], $this->parser->push(substr($wire, 14, 1_000_000)));
        $this->assertSame([], $this->parser->push(substr($wire, 1_000_014, 1_000_000)));
        
        // Final payload chunk
        $frames = $this->parser->push(substr($wire, 2_000_014));

        $this->assertCount(1, $frames);
        $this->assertSame(2_097_152, strlen($frames[0]->payload));
    }

    #[Test]
    public function it_rejects_payload_one_byte_over_maximum_limit(): void
    {
        // One byte over the 2,097,152 limit
        // We only push the header, length, and mask. The parser must fail immediately
        // upon parsing the length, before the complete oversized payload arrives.
        $wireHeader = pack('CCJ', 0x81, 0x80 | 127, 2_097_153) . "\x11\x22\x33\x44";

        $this->expectException(LimitExceededException::class);
        $this->expectExceptionMessage('Frame payload size 2097153 exceeds maximum');

        $this->parser->push($wireHeader);
    }

    #[Test]
    public function it_enforces_exact_maximum_buffer_boundary(): void
    {
        // Construct a chunk of exactly 2,097,166 bytes
        $chunk = str_repeat('x', 2_097_166);
        
        // Because 2,097,166 <= MAX_BUFFER, it should NOT throw LimitExceededException.
        // Instead, it will append to buffer and try to parse the first byte 'x' (0x78).
        // 0x78 has RSV bits set, so it will throw ProtocolException.
        // This proves the buffer limit check was successfully passed.
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('RSV bits must not be set');

        $this->parser->push($chunk);
    }
}
