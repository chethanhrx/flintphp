<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\WebSocket\Message;

use FlintPHP\Framework\WebSocket\Exception\InvalidUtf8Exception;
use FlintPHP\Framework\WebSocket\Exception\LimitExceededException;
use FlintPHP\Framework\WebSocket\Exception\ProtocolException;
use FlintPHP\Framework\WebSocket\Frame\Frame;
use FlintPHP\Framework\WebSocket\Frame\Opcode;
use FlintPHP\Framework\WebSocket\Message\Message;
use FlintPHP\Framework\WebSocket\Message\MessageAssembler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MessageAssembler::class)]
final class MessageAssemblerTest extends TestCase
{
    private MessageAssembler $assembler;

    protected function setUp(): void
    {
        $this->assembler = new MessageAssembler();
    }

    private function createFrame(Opcode $opcode, string $payload, bool $fin = true): Frame
    {
        return new Frame(
            fin: $fin,
            rsv1: false,
            rsv2: false,
            rsv3: false,
            opcode: $opcode,
            masked: true, // Assuming properly parsed frame
            payload: $payload
        );
    }

    #[Test]
    public function it_assembles_unfragmented_text_message(): void
    {
        $frame = $this->createFrame(Opcode::TEXT, 'hello world');
        $message = $this->assembler->push($frame);

        $this->assertInstanceOf(Message::class, $message);
        $this->assertTrue($message->text);
        $this->assertSame('hello world', $message->payload);
    }

    #[Test]
    public function it_assembles_unfragmented_binary_message(): void
    {
        $payload = pack('C*', 0x00, 0xFF, 0x80, 0xC0, 0xFE);
        $frame = $this->createFrame(Opcode::BINARY, $payload);
        $message = $this->assembler->push($frame);

        $this->assertInstanceOf(Message::class, $message);
        $this->assertFalse($message->text);
        $this->assertSame($payload, $message->payload);
    }

    #[Test]
    public function it_assembles_empty_messages(): void
    {
        $textMsg = $this->assembler->push($this->createFrame(Opcode::TEXT, ''));
        $this->assertInstanceOf(Message::class, $textMsg);
        $this->assertTrue($textMsg->text);
        $this->assertSame('', $textMsg->payload);

        $binMsg = $this->assembler->push($this->createFrame(Opcode::BINARY, ''));
        $this->assertInstanceOf(Message::class, $binMsg);
        $this->assertFalse($binMsg->text);
        $this->assertSame('', $binMsg->payload);
    }

    #[Test]
    public function it_assembles_fragmented_text_message(): void
    {
        $f1 = $this->assembler->push($this->createFrame(Opcode::TEXT, 'Hel', false));
        $this->assertNull($f1);

        $f2 = $this->assembler->push($this->createFrame(Opcode::CONTINUATION, 'lo ', false));
        $this->assertNull($f2);

        $f3 = $this->assembler->push($this->createFrame(Opcode::CONTINUATION, 'World', true));
        $this->assertInstanceOf(Message::class, $f3);
        $this->assertTrue($f3->text);
        $this->assertSame('Hello World', $f3->payload);
    }

    #[Test]
    public function it_assembles_fragmented_binary_message(): void
    {
        $this->assertNull($this->assembler->push($this->createFrame(Opcode::BINARY, "\x00\xFF", false)));
        $this->assertNull($this->assembler->push($this->createFrame(Opcode::CONTINUATION, "\x80", false)));
        $msg = $this->assembler->push($this->createFrame(Opcode::CONTINUATION, "\xC0\xFE", true));

        $this->assertInstanceOf(Message::class, $msg);
        $this->assertFalse($msg->text);
        $this->assertSame("\x00\xFF\x80\xC0\xFE", $msg->payload);
    }

    #[Test]
    public function it_allows_multiple_messages_in_sequence(): void
    {
        // Message A (unfragmented)
        $msgA = $this->assembler->push($this->createFrame(Opcode::TEXT, 'A'));
        $this->assertSame('A', $msgA->payload);

        // Message B (fragmented)
        $this->assembler->push($this->createFrame(Opcode::TEXT, 'B', false));
        $msgB = $this->assembler->push($this->createFrame(Opcode::CONTINUATION, 'C', true));
        $this->assertSame('BC', $msgB->payload);

        // Message C (unfragmented binary)
        $msgC = $this->assembler->push($this->createFrame(Opcode::BINARY, 'D'));
        $this->assertSame('D', $msgC->payload);
    }

    #[Test]
    public function it_ignores_control_frames_during_fragmentation(): void
    {
        $this->assertNull($this->assembler->push($this->createFrame(Opcode::TEXT, 'Hel', false)));
        
        // PING
        $this->assertNull($this->assembler->push($this->createFrame(Opcode::PING, 'ping')));
        
        $this->assertNull($this->assembler->push($this->createFrame(Opcode::CONTINUATION, 'lo ', false)));
        
        // PONG
        $this->assertNull($this->assembler->push($this->createFrame(Opcode::PONG, 'pong')));
        
        // CLOSE
        $this->assertNull($this->assembler->push($this->createFrame(Opcode::CLOSE, 'close')));
        
        $msg = $this->assembler->push($this->createFrame(Opcode::CONTINUATION, 'World', true));
        
        $this->assertInstanceOf(Message::class, $msg);
        $this->assertSame('Hello World', $msg->payload);
    }

    #[Test]
    public function it_rejects_continuation_without_active_message(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('CONTINUATION frame received without an active');
        $this->assembler->push($this->createFrame(Opcode::CONTINUATION, 'data', true));
    }

    #[Test]
    public function it_rejects_text_during_fragmented_message(): void
    {
        $this->assembler->push($this->createFrame(Opcode::TEXT, 'part1', false));
        
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('TEXT or BINARY frame received during an active');
        $this->assembler->push($this->createFrame(Opcode::TEXT, 'part2', true));
    }

    #[Test]
    public function it_rejects_binary_during_fragmented_message(): void
    {
        $this->assembler->push($this->createFrame(Opcode::BINARY, 'part1', false));
        
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('TEXT or BINARY frame received during an active');
        $this->assembler->push($this->createFrame(Opcode::BINARY, 'part2', false));
    }

    #[Test]
    public function it_validates_utf8_on_complete_text_message(): void
    {
        // Invalid UTF-8 sequence
        $invalidUtf8 = "hello \x80 world";
        
        $this->expectException(InvalidUtf8Exception::class);
        $this->assembler->push($this->createFrame(Opcode::TEXT, $invalidUtf8, true));
    }

    #[Test]
    public function it_allows_invalid_utf8_in_binary_messages(): void
    {
        $invalidUtf8 = "hello \x80 world";
        $msg = $this->assembler->push($this->createFrame(Opcode::BINARY, $invalidUtf8, true));
        $this->assertInstanceOf(Message::class, $msg);
        $this->assertSame($invalidUtf8, $msg->payload);
    }

    #[Test]
    public function it_accepts_split_utf8_characters_across_fragments(): void
    {
        // 🌍 Earth emoji (U+1F30D) is 4 bytes: "\xF0\x9F\x8C\x8D"
        $emoji = "\xF0\x9F\x8C\x8D";

        $this->assembler->push($this->createFrame(Opcode::TEXT, substr($emoji, 0, 2), false));
        $msg = $this->assembler->push($this->createFrame(Opcode::CONTINUATION, substr($emoji, 2, 2), true));

        $this->assertInstanceOf(Message::class, $msg);
        $this->assertSame($emoji, $msg->payload);
    }

    #[Test]
    public function it_rejects_invalid_utf8_after_reassembly(): void
    {
        // Truncated UTF-8 emoji
        $emojiTruncated = "\xF0\x9F\x8C";

        $this->assembler->push($this->createFrame(Opcode::TEXT, substr($emojiTruncated, 0, 2), false));
        
        $this->expectException(InvalidUtf8Exception::class);
        $this->assembler->push($this->createFrame(Opcode::CONTINUATION, substr($emojiTruncated, 2, 1), true));
    }

    #[Test]
    public function it_enforces_10mib_limit_across_fragments(): void
    {
        // 5 frames of exactly 2,097,152 bytes = 10,485,760 bytes (10 MiB)
        $this->assembler->push($this->createFrame(Opcode::BINARY, str_repeat('a', 2_097_152), false));
        
        for ($i = 0; $i < 4; $i++) {
            $this->assembler->push($this->createFrame(Opcode::CONTINUATION, str_repeat('a', 2_097_152), false));
        }
        
        // Sixth frame: 1 byte, exceeding 10 MiB limit
        $this->expectException(LimitExceededException::class);
        $this->expectExceptionMessage('Message size exceeds 10 MiB limit');
        $this->assembler->push($this->createFrame(Opcode::CONTINUATION, 'a', true));
    }

    #[Test]
    public function it_enforces_1000_fragment_limit(): void
    {
        $this->assembler->push($this->createFrame(Opcode::TEXT, 'a', false));
        
        // 998 continuations -> 999 fragments total
        for ($i = 0; $i < 998; $i++) {
            $this->assembler->push($this->createFrame(Opcode::CONTINUATION, 'a', false));
        }

        // 1000th fragment is allowed
        $this->assertNull($this->assembler->push($this->createFrame(Opcode::CONTINUATION, 'a', false)));
        
        // 1001st fragment is rejected
        $this->expectException(LimitExceededException::class);
        $this->expectExceptionMessage('Message fragment count exceeds limit of 1000');
        $this->assembler->push($this->createFrame(Opcode::CONTINUATION, 'a', true));
    }

    #[Test]
    public function it_stays_in_error_state_after_failure(): void
    {
        try {
            $this->assembler->push($this->createFrame(Opcode::CONTINUATION, 'data', true));
            $this->fail('Expected exception');
        } catch (ProtocolException) {
        }

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Assembler is in an error state');
        $this->assembler->push($this->createFrame(Opcode::TEXT, 'data', true));
    }
}
