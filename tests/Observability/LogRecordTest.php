<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Observability;

use FlintPHP\Framework\Observability\Contract\LogLevel;
use FlintPHP\Framework\Observability\Contract\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LogRecord::class)]
final class LogRecordTest extends TestCase
{
    #[Test]
    public function it_preserves_required_values(): void
    {
        $record = new LogRecord(
            level: LogLevel::ERROR,
            message: 'Something failed',
        );

        $this->assertSame(LogLevel::ERROR, $record->level);
        $this->assertSame('Something failed', $record->message);
    }

    #[Test]
    public function it_uses_default_channel(): void
    {
        $record = new LogRecord(LogLevel::INFO, 'test');
        $this->assertSame('app', $record->channel);
    }

    #[Test]
    public function it_accepts_custom_channel(): void
    {
        $record = new LogRecord(LogLevel::INFO, 'test', channel: 'auth');
        $this->assertSame('auth', $record->channel);
    }

    #[Test]
    public function it_creates_default_timestamp(): void
    {
        $before = new \DateTimeImmutable();
        $record = new LogRecord(LogLevel::INFO, 'test');
        $after = new \DateTimeImmutable();

        $this->assertInstanceOf(\DateTimeImmutable::class, $record->timestamp);
        $this->assertGreaterThanOrEqual($before, $record->timestamp);
        $this->assertLessThanOrEqual($after, $record->timestamp);
    }

    #[Test]
    public function it_preserves_supplied_timestamp(): void
    {
        $ts = new \DateTimeImmutable('2025-01-15T12:00:00+00:00');
        $record = new LogRecord(LogLevel::INFO, 'test', timestamp: $ts);

        $this->assertSame($ts, $record->timestamp);
    }

    #[Test]
    public function it_preserves_context(): void
    {
        $context = ['user_id' => 42, 'action' => 'login'];
        $record = new LogRecord(LogLevel::INFO, 'test', context: $context);

        $this->assertSame($context, $record->context);
    }

    #[Test]
    public function it_defaults_to_empty_context(): void
    {
        $record = new LogRecord(LogLevel::INFO, 'test');
        $this->assertSame([], $record->context);
    }

    #[Test]
    public function it_preserves_context_containing_binary_strings(): void
    {
        $binary = "\x00\xFF\x80\xC0\xFE";
        $context = ['raw' => $binary];
        $record = new LogRecord(LogLevel::INFO, 'test', context: $context);

        $this->assertSame($binary, $record->context['raw']);
    }

    #[Test]
    public function it_preserves_context_containing_unusual_php_values(): void
    {
        $context = [
            'null' => null,
            'false' => false,
            'zero' => 0,
            'empty' => '',
            'float' => 0.0,
        ];
        $record = new LogRecord(LogLevel::INFO, 'test', context: $context);

        $this->assertSame($context, $record->context);
    }

    #[Test]
    public function it_allows_empty_message(): void
    {
        $record = new LogRecord(LogLevel::DEBUG, '');
        $this->assertSame('', $record->message);
    }

    #[Test]
    public function it_preserves_nested_array_context(): void
    {
        $context = ['data' => ['nested' => ['deep' => true]]];
        $record = new LogRecord(LogLevel::INFO, 'test', context: $context);
        $this->assertSame($context, $record->context);
    }

    #[Test]
    public function it_preserves_context_containing_objects(): void
    {
        $obj = new \stdClass();
        $obj->name = 'test';
        $context = ['object' => $obj];
        $record = new LogRecord(LogLevel::INFO, 'test', context: $context);

        // Object reference is preserved, not serialized
        $this->assertSame($obj, $record->context['object']);
    }
}
