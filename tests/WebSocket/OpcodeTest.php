<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\WebSocket;

use FlintPHP\Framework\WebSocket\Frame\Opcode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Opcode::class)]
final class OpcodeTest extends TestCase
{
    #[Test]
    public function it_verifies_exact_integer_values(): void
    {
        $this->assertSame(0x0, Opcode::CONTINUATION->value);
        $this->assertSame(0x1, Opcode::TEXT->value);
        $this->assertSame(0x2, Opcode::BINARY->value);
        $this->assertSame(0x8, Opcode::CLOSE->value);
        $this->assertSame(0x9, Opcode::PING->value);
        $this->assertSame(0xA, Opcode::PONG->value);
    }

    #[Test]
    public function it_resolves_valid_values_correctly(): void
    {
        $this->assertSame(Opcode::TEXT, Opcode::from(0x1));
        $this->assertSame(Opcode::CLOSE, Opcode::from(0x8));
    }

    #[Test]
    public function it_does_not_resolve_reserved_values(): void
    {
        $this->assertNull(Opcode::tryFrom(0x3));
        $this->assertNull(Opcode::tryFrom(0x7));
        $this->assertNull(Opcode::tryFrom(0xB));
        $this->assertNull(Opcode::tryFrom(0xF));
    }
}
