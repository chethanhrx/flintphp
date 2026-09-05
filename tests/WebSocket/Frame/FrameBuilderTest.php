<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\WebSocket\Frame;

use FlintPHP\Framework\WebSocket\Exception\LimitExceededException;
use FlintPHP\Framework\WebSocket\Exception\ProtocolException;
use FlintPHP\Framework\WebSocket\Frame\FrameBuilder;
use FlintPHP\Framework\WebSocket\Frame\Opcode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FrameBuilder::class)]
final class FrameBuilderTest extends TestCase
{
    private FrameBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new FrameBuilder();
    }

    #[Test]
    public function it_builds_empty_text_final_frame(): void
    {
        $frame = $this->builder->buildServerFrame(Opcode::TEXT, '', true);
        
        // 0x81 0x00
        $this->assertSame(pack('C*', 0x81, 0x00), $frame);
    }

    #[Test]
    public function it_builds_hello_text_final_frame(): void
    {
        $frame = $this->builder->buildServerFrame(Opcode::TEXT, 'Hello', true);
        
        // 0x81 0x05 48 65 6c 6c 6f
        $expected = pack('C*', 0x81, 0x05, 0x48, 0x65, 0x6c, 0x6c, 0x6f);
        $this->assertSame($expected, $frame);
    }

    #[Test]
    public function it_builds_empty_ping_frame(): void
    {
        $frame = $this->builder->buildServerFrame(Opcode::PING, '', true);
        
        // 0x89 0x00
        $this->assertSame(pack('C*', 0x89, 0x00), $frame);
    }

    #[Test]
    public function it_builds_empty_close_frame(): void
    {
        $frame = $this->builder->buildServerFrame(Opcode::CLOSE, '', true);
        
        // 0x88 0x00
        $this->assertSame(pack('C*', 0x88, 0x00), $frame);
    }

    #[Test]
    public function it_builds_payload_length_125(): void
    {
        $payload = str_repeat('a', 125);
        $frame = $this->builder->buildServerFrame(Opcode::TEXT, $payload, true);
        
        $this->assertSame(pack('C*', 0x81, 125) . $payload, $frame);
    }

    #[Test]
    public function it_builds_payload_length_126(): void
    {
        $payload = str_repeat('a', 126);
        $frame = $this->builder->buildServerFrame(Opcode::TEXT, $payload, true);
        
        // 0x81 0x7e 0x00 0x7e (126)
        $this->assertSame(pack('C*', 0x81, 0x7e, 0x00, 0x7e) . $payload, $frame);
    }

    #[Test]
    public function it_builds_payload_length_127(): void
    {
        $payload = str_repeat('a', 127);
        $frame = $this->builder->buildServerFrame(Opcode::TEXT, $payload, true);
        
        // 0x81 0x7e 0x00 0x7f (127)
        $this->assertSame(pack('C*', 0x81, 0x7e, 0x00, 0x7f) . $payload, $frame);
    }

    #[Test]
    public function it_builds_payload_length_65535(): void
    {
        $payload = str_repeat('a', 65535);
        $frame = $this->builder->buildServerFrame(Opcode::BINARY, $payload, true);
        
        // 0x82 0x7e 0xff 0xff
        $this->assertSame(pack('CCn', 0x82, 0x7e, 65535) . $payload, $frame);
    }

    #[Test]
    public function it_builds_payload_length_65536(): void
    {
        $payload = str_repeat('a', 65536);
        $frame = $this->builder->buildServerFrame(Opcode::BINARY, $payload, true);
        
        // 0x82 0x7f followed by 8 byte length
        $expectedHeader = pack('CCJ', 0x82, 0x7f, 65536);
        $this->assertSame($expectedHeader . $payload, $frame);
    }

    #[Test]
    public function it_builds_payload_length_2_097_152(): void
    {
        $payload = str_repeat('a', 2_097_152);
        $frame = $this->builder->buildServerFrame(Opcode::BINARY, $payload, true);
        
        $expectedHeader = pack('CCJ', 0x82, 0x7f, 2_097_152);
        $this->assertSame($expectedHeader . $payload, $frame);
    }

    #[Test]
    public function it_rejects_payload_length_above_limit(): void
    {
        $payload = str_repeat('a', 2_097_153);
        $this->expectException(LimitExceededException::class);
        $this->builder->buildServerFrame(Opcode::BINARY, $payload, true);
    }

    #[Test]
    public function it_rejects_control_frame_fragmentation(): void
    {
        $this->expectException(ProtocolException::class);
        $this->builder->buildServerFrame(Opcode::PING, 'ping', false);
    }

    #[Test]
    public function it_rejects_control_frame_oversized(): void
    {
        $this->expectException(ProtocolException::class);
        $this->builder->buildServerFrame(Opcode::PONG, str_repeat('a', 126), true);
    }

    #[Test]
    public function it_rejects_close_frame_length_1(): void
    {
        $this->expectException(ProtocolException::class);
        $this->builder->buildServerFrame(Opcode::CLOSE, 'a', true);
    }

    #[Test]
    public function it_builds_fragmented_data_frames(): void
    {
        $frame = $this->builder->buildServerFrame(Opcode::TEXT, 'frag', false);
        // 0x01 (FIN=0, TEXT=1), 0x04 (length 4)
        $this->assertSame(pack('C*', 0x01, 0x04) . 'frag', $frame);
    }

    #[Test]
    public function it_supports_arbitrary_binary_payloads(): void
    {
        // Payload with zero bytes and bytes >= 0x80
        $payload = pack('C*', 0x00, 0x7f, 0x80, 0xff, 0x00);
        $frame = $this->builder->buildServerFrame(Opcode::BINARY, $payload, true);
        
        $this->assertSame(pack('C*', 0x82, 0x05) . $payload, $frame);
    }
}
