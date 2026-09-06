<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Observability;

use FlintPHP\Framework\Observability\Contract\LoggerInterface;
use FlintPHP\Framework\Observability\Contract\LogLevel;
use FlintPHP\Framework\Observability\Contract\LogRecord;
use FlintPHP\Framework\Observability\Exception\ObservabilityException;
use FlintPHP\Framework\Observability\Logging\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Logger::class)]
final class LoggerTest extends TestCase
{
    // ── Interface contract ──

    #[Test]
    public function it_implements_logger_interface(): void
    {
        $logger = new Logger();
        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    // ── Channel validation ──

    #[Test]
    public function it_uses_default_channel(): void
    {
        $logger = new Logger();
        $logger->info('test');
        $this->assertSame('app', $logger->records()[0]->channel);
    }

    #[Test]
    public function it_accepts_custom_channel(): void
    {
        $logger = new Logger('security');
        $logger->info('test');
        $this->assertSame('security', $logger->records()[0]->channel);
    }

    #[Test]
    public function it_accepts_channel_with_dots_underscores_hyphens(): void
    {
        $logger = new Logger('my-app.worker_1');
        $logger->info('test');
        $this->assertSame('my-app.worker_1', $logger->records()[0]->channel);
    }

    #[Test]
    public function it_accepts_channel_at_max_length(): void
    {
        $channel = str_repeat('a', 64);
        $logger = new Logger($channel);
        $logger->info('test');
        $this->assertSame($channel, $logger->records()[0]->channel);
    }

    #[Test]
    public function it_rejects_empty_channel(): void
    {
        $this->expectException(ObservabilityException::class);
        new Logger('');
    }

    #[Test]
    public function it_rejects_channel_over_64_chars(): void
    {
        $this->expectException(ObservabilityException::class);
        new Logger(str_repeat('a', 65));
    }

    #[Test]
    public function it_rejects_channel_with_spaces(): void
    {
        $this->expectException(ObservabilityException::class);
        new Logger('my app');
    }

    #[Test]
    public function it_rejects_channel_with_slashes(): void
    {
        $this->expectException(ObservabilityException::class);
        new Logger('my/app');
    }

    #[Test]
    public function it_rejects_channel_with_backslashes(): void
    {
        $this->expectException(ObservabilityException::class);
        new Logger('my\\app');
    }

    #[Test]
    public function it_rejects_channel_with_control_chars(): void
    {
        $this->expectException(ObservabilityException::class);
        new Logger("app\x00");
    }

    #[Test]
    public function it_rejects_channel_with_newline(): void
    {
        $this->expectException(ObservabilityException::class);
        new Logger("app\n");
    }

    #[Test]
    public function it_rejects_channel_with_tab(): void
    {
        $this->expectException(ObservabilityException::class);
        new Logger("app\t");
    }

    // ── Convenience methods create correct levels ──

    #[Test]
    public function it_creates_debug_record(): void
    {
        $logger = new Logger();
        $logger->debug('d');
        $this->assertSame(LogLevel::DEBUG, $logger->records()[0]->level);
    }

    #[Test]
    public function it_creates_info_record(): void
    {
        $logger = new Logger();
        $logger->info('i');
        $this->assertSame(LogLevel::INFO, $logger->records()[0]->level);
    }

    #[Test]
    public function it_creates_notice_record(): void
    {
        $logger = new Logger();
        $logger->notice('n');
        $this->assertSame(LogLevel::NOTICE, $logger->records()[0]->level);
    }

    #[Test]
    public function it_creates_warning_record(): void
    {
        $logger = new Logger();
        $logger->warning('w');
        $this->assertSame(LogLevel::WARNING, $logger->records()[0]->level);
    }

    #[Test]
    public function it_creates_error_record(): void
    {
        $logger = new Logger();
        $logger->error('e');
        $this->assertSame(LogLevel::ERROR, $logger->records()[0]->level);
    }

    #[Test]
    public function it_creates_critical_record(): void
    {
        $logger = new Logger();
        $logger->critical('c');
        $this->assertSame(LogLevel::CRITICAL, $logger->records()[0]->level);
    }

    #[Test]
    public function it_creates_alert_record(): void
    {
        $logger = new Logger();
        $logger->alert('a');
        $this->assertSame(LogLevel::ALERT, $logger->records()[0]->level);
    }

    #[Test]
    public function it_creates_emergency_record(): void
    {
        $logger = new Logger();
        $logger->emergency('e');
        $this->assertSame(LogLevel::EMERGENCY, $logger->records()[0]->level);
    }

    // ── Message and context preservation ──

    #[Test]
    public function it_preserves_exact_message(): void
    {
        $logger = new Logger();
        $logger->info('Hello, World!');
        $this->assertSame('Hello, World!', $logger->records()[0]->message);
    }

    #[Test]
    public function it_preserves_message_with_newlines_and_control_chars(): void
    {
        $msg = "line1\nline2\r\ttab\x00null";
        $logger = new Logger();
        $logger->info($msg);
        $this->assertSame($msg, $logger->records()[0]->message);
    }

    #[Test]
    public function it_preserves_exact_context(): void
    {
        $context = ['key' => 'value', 'nested' => ['a' => 1]];
        $logger = new Logger();
        $logger->info('test', $context);
        $this->assertSame($context, $logger->records()[0]->context);
    }

    #[Test]
    public function it_preserves_context_containing_objects_without_serialization(): void
    {
        $obj = new \stdClass();
        $obj->secret = 'password';
        $logger = new Logger();
        $logger->info('test', ['obj' => $obj]);

        // The object reference is retained, not serialized
        $this->assertSame($obj, $logger->records()[0]->context['obj']);
    }

    #[Test]
    public function it_stores_throwable_in_context_opaquely_without_interpretation(): void
    {
        $ex = new \RuntimeException('boom');
        $logger = new Logger();
        $logger->error('fail', ['exception' => $ex]);

        // The Throwable is stored as an opaque context value, not inspected or serialized
        $this->assertSame($ex, $logger->records()[0]->context['exception']);
    }

    // ── Record ordering and storage ──

    #[Test]
    public function it_preserves_record_order(): void
    {
        $logger = new Logger();
        $logger->info('A');
        $logger->error('B');
        $logger->debug('C');

        $records = $logger->records();
        $this->assertCount(3, $records);
        $this->assertSame('A', $records[0]->message);
        $this->assertSame('B', $records[1]->message);
        $this->assertSame('C', $records[2]->message);
    }

    #[Test]
    public function it_retains_multiple_records(): void
    {
        $logger = new Logger();
        for ($i = 0; $i < 100; $i++) {
            $logger->info("msg-$i");
        }
        $this->assertCount(100, $logger->records());
    }

    // ── records() isolation ──

    #[Test]
    public function modifying_returned_array_does_not_mutate_internals(): void
    {
        $logger = new Logger();
        $logger->info('A');

        $records = $logger->records();
        $records[] = new LogRecord(LogLevel::DEBUG, 'injected');

        // Logger's internal state is unaffected
        $this->assertCount(1, $logger->records());
    }

    // ── clear() ──

    #[Test]
    public function clear_removes_all_records(): void
    {
        $logger = new Logger();
        $logger->info('A');
        $logger->error('B');
        $this->assertCount(2, $logger->records());

        $logger->clear();
        $this->assertCount(0, $logger->records());
    }

    #[Test]
    public function clear_only_affects_that_instance(): void
    {
        $loggerA = new Logger('a');
        $loggerB = new Logger('b');

        $loggerA->info('A');
        $loggerB->info('B');

        $loggerA->clear();

        $this->assertCount(0, $loggerA->records());
        $this->assertCount(1, $loggerB->records());
    }

    // ── Instance isolation ──

    #[Test]
    public function separate_instances_are_isolated(): void
    {
        $a = new Logger('a');
        $b = new Logger('b');

        $a->info('only-a');

        $this->assertCount(1, $a->records());
        $this->assertCount(0, $b->records());
    }

    // ── Timestamp ──

    #[Test]
    public function records_have_datetimeimmutable_timestamps(): void
    {
        $logger = new Logger();
        $logger->info('test');

        $this->assertInstanceOf(\DateTimeImmutable::class, $logger->records()[0]->timestamp);
    }

    #[Test]
    public function timestamps_are_ordered(): void
    {
        $logger = new Logger();
        $logger->info('first');
        $logger->info('second');

        $records = $logger->records();
        $this->assertGreaterThanOrEqual($records[0]->timestamp, $records[1]->timestamp);
    }
}
