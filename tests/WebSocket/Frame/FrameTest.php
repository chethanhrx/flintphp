<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\WebSocket\Frame;

use FlintPHP\Framework\WebSocket\Exception\LimitExceededException;
use FlintPHP\Framework\WebSocket\Exception\ProtocolException;
use FlintPHP\Framework\WebSocket\Frame\Frame;
use FlintPHP\Framework\WebSocket\Frame\Opcode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Frame::class)]
final class FrameTest extends TestCase
{
    #[Test]
    public function it_constructs_valid_frames_and_preserves_properties(): void
    {
        $frame = new Frame(
            fin: true,
            rsv1: false,
            rsv2: false,
            rsv3: false,
            opcode: Opcode::TEXT,
            masked: true,
            payload: 'hello',
        );

        $this->assertTrue($frame->fin);
        $this->assertFalse($frame->rsv1);
        $this->assertFalse($frame->rsv2);
        $this->assertFalse($frame->rsv3);
        $this->assertSame(Opcode::TEXT, $frame->opcode);
        $this->assertTrue($frame->masked);
        $this->assertSame('hello', $frame->payload);

        // FIN false is valid for TEXT
        $frameFragment = new Frame(
            fin: false,
            rsv1: false,
            rsv2: false,
            rsv3: false,
            opcode: Opcode::TEXT,
            masked: false,
            payload: '',
        );
        $this->assertFalse($frameFragment->fin);
        $this->assertSame('', $frameFragment->payload);
        $this->assertFalse($frameFragment->masked);
    }

    #[Test]
    public function it_rejects_rsv_bits(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('RSV bits must not be set');

        new Frame(
            fin: true,
            rsv1: true,
            rsv2: false,
            rsv3: false,
            opcode: Opcode::TEXT,
            masked: false,
            payload: '',
        );
    }

    #[Test]
    public function it_rejects_rsv2(): void
    {
        $this->expectException(ProtocolException::class);
        new Frame(true, false, true, false, Opcode::TEXT, false, '');
    }

    #[Test]
    public function it_rejects_rsv3(): void
    {
        $this->expectException(ProtocolException::class);
        new Frame(true, false, false, true, Opcode::TEXT, false, '');
    }

    #[Test]
    public function it_enforces_control_frame_fin_true(): void
    {
        // Accepted
        new Frame(true, false, false, false, Opcode::PING, false, 'ping');
        new Frame(true, false, false, false, Opcode::PONG, false, 'pong');
        new Frame(true, false, false, false, Opcode::CLOSE, false, 'ab');

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Control frames must not be fragmented');

        // Rejected
        new Frame(false, false, false, false, Opcode::PING, false, 'ping');
    }

    #[Test]
    public function it_enforces_control_payload_boundary(): void
    {
        // 125 bytes -> accepted
        new Frame(true, false, false, false, Opcode::PING, false, str_repeat('a', 125));

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Control frame payload must not exceed 125 bytes');

        // 126 bytes -> rejected
        new Frame(true, false, false, false, Opcode::PING, false, str_repeat('a', 126));
    }

    #[Test]
    public function it_enforces_close_payload_boundary(): void
    {
        // 0 bytes -> accepted
        new Frame(true, false, false, false, Opcode::CLOSE, false, '');
        
        // 2 bytes -> accepted
        new Frame(true, false, false, false, Opcode::CLOSE, false, 'ab');

        // 125 bytes -> accepted
        new Frame(true, false, false, false, Opcode::CLOSE, false, str_repeat('a', 125));

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Close frame payload length cannot be exactly 1 byte');

        // 1 byte -> rejected
        new Frame(true, false, false, false, Opcode::CLOSE, false, 'a');
    }

    #[Test]
    public function it_enforces_frame_payload_limit(): void
    {
        // Limit is 2_097_152.
        new Frame(true, false, false, false, Opcode::BINARY, false, str_repeat('a', 2_097_152));

        $this->expectException(LimitExceededException::class);
        $this->expectExceptionMessage('Frame payload exceeds maximum allowed size');

        new Frame(true, false, false, false, Opcode::BINARY, false, str_repeat('a', 2_097_153));
    }

    #[Test]
    public function it_accepts_all_defined_opcodes(): void
    {
        $opcodes = Opcode::cases();
        foreach ($opcodes as $opcode) {
            $frame = new Frame(true, false, false, false, $opcode, false, $opcode === Opcode::CLOSE ? '' : 'test');
            $this->assertSame($opcode, $frame->opcode);
        }
    }
}
