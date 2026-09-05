<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\WebSocket\Message;

use FlintPHP\Framework\WebSocket\Message\Message;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Message::class)]
final class MessageTest extends TestCase
{
    #[Test]
    public function it_constructs_text_message(): void
    {
        $message = new Message('hello world', true);
        
        $this->assertSame('hello world', $message->payload);
        $this->assertTrue($message->text);
    }

    #[Test]
    public function it_constructs_binary_message(): void
    {
        $payload = pack('C*', 0x00, 0xFF, 0x80, 0xC0, 0xFE);
        $message = new Message($payload, false);
        
        $this->assertSame($payload, $message->payload);
        $this->assertFalse($message->text);
    }

    #[Test]
    public function it_constructs_empty_message(): void
    {
        $message = new Message('', true);
        
        $this->assertSame('', $message->payload);
        $this->assertTrue($message->text);
    }
}
