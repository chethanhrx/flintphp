<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Http;

use FlintPHP\Framework\Http\HeaderBag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(HeaderBag::class)]
final class HeaderBagControlCharacterTest extends TestCase
{
    #[Test]
    public function crlf_injection_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new HeaderBag(['X-Test' => "safe\r\nSet-Cookie: injected=1"]);
    }

    #[Test]
    public function lone_carriage_return_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new HeaderBag(['X-Test' => "a\rb"]);
    }

    #[Test]
    public function nul_byte_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('control characters');

        new HeaderBag(['X-Test' => "a\0b"]);
    }

    #[Test]
    public function other_c0_control_characters_are_rejected(): void
    {
        foreach (["\x00", "\x01", "\x1F", "\x7F"] as $control) {
            try {
                new HeaderBag(['X-Test' => 'a' . $control . 'b']);
                $this->fail(sprintf('Control character 0x%02X was accepted.', ord($control)));
            } catch (\InvalidArgumentException) {
                // Expected.
            }
        }

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function withHeader_applies_the_same_validation(): void
    {
        $bag = new HeaderBag(['X-Test' => 'safe']);

        $this->expectException(\InvalidArgumentException::class);

        $bag->withHeader('X-Other', "value\0nul");
    }

    #[Test]
    public function legitimate_header_values_remain_accepted(): void
    {
        $bag = new HeaderBag([
            'Content-Type' => 'application/json; charset=utf-8',
            'X-Obs-Text' => "café — 0x80-0xFF range is allowed",
            'X-Tab' => "a\tb",
        ]);

        $this->assertSame('application/json; charset=utf-8', $bag->get('Content-Type'));
        $this->assertSame("a\tb", $bag->get('X-Tab'));
    }
}
