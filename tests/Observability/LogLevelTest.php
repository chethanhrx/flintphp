<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Observability;

use FlintPHP\Framework\Observability\Contract\LogLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LogLevel::class)]
final class LogLevelTest extends TestCase
{
    #[Test]
    public function it_has_exactly_eight_levels(): void
    {
        $this->assertCount(8, LogLevel::cases());
    }

    #[Test]
    public function it_has_correct_backing_values(): void
    {
        $this->assertSame('debug', LogLevel::DEBUG->value);
        $this->assertSame('info', LogLevel::INFO->value);
        $this->assertSame('notice', LogLevel::NOTICE->value);
        $this->assertSame('warning', LogLevel::WARNING->value);
        $this->assertSame('error', LogLevel::ERROR->value);
        $this->assertSame('critical', LogLevel::CRITICAL->value);
        $this->assertSame('alert', LogLevel::ALERT->value);
        $this->assertSame('emergency', LogLevel::EMERGENCY->value);
    }

    #[Test]
    public function it_supports_from_string(): void
    {
        $this->assertSame(LogLevel::DEBUG, LogLevel::from('debug'));
        $this->assertSame(LogLevel::EMERGENCY, LogLevel::from('emergency'));
    }

    #[Test]
    public function it_returns_null_for_invalid_tryFrom(): void
    {
        $this->assertNull(LogLevel::tryFrom('INVALID'));
        $this->assertNull(LogLevel::tryFrom(''));
        $this->assertNull(LogLevel::tryFrom('DEBUG')); // Case-sensitive
    }

    #[Test]
    public function enum_identity_is_preserved(): void
    {
        $a = LogLevel::ERROR;
        $b = LogLevel::ERROR;
        $this->assertSame($a, $b);
        $this->assertTrue($a === $b);
    }
}
