<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Observability;

use FlintPHP\Framework\Observability\Contract\LoggerInterface;
use FlintPHP\Framework\Observability\Contract\LogLevel;
use FlintPHP\Framework\Observability\Logging\NullLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NullLogger::class)]
final class NullLoggerTest extends TestCase
{
    #[Test]
    public function it_implements_logger_interface(): void
    {
        $this->assertInstanceOf(LoggerInterface::class, new NullLogger());
    }

    #[Test]
    public function all_levels_are_noop(): void
    {
        $logger = new NullLogger();

        // None of these should throw or produce side effects
        $logger->log(LogLevel::DEBUG, 'test', ['key' => 'value']);
        $logger->debug('test');
        $logger->info('test');
        $logger->notice('test');
        $logger->warning('test');
        $logger->error('test');
        $logger->critical('test');
        $logger->alert('test');
        $logger->emergency('test');

        // If we reach here without throwing, the test passes
        $this->assertTrue(true);
    }

    #[Test]
    public function repeated_calls_remain_noop(): void
    {
        $logger = new NullLogger();

        for ($i = 0; $i < 1000; $i++) {
            $logger->info("message-$i");
        }

        $this->assertTrue(true);
    }

    #[Test]
    public function independent_instances_are_isolated(): void
    {
        $a = new NullLogger();
        $b = new NullLogger();

        $a->info('a');
        $b->info('b');

        // Both are independent no-ops
        $this->assertNotSame($a, $b);
    }

    #[Test]
    public function it_accepts_complex_context_without_error(): void
    {
        $logger = new NullLogger();

        $logger->info('test', [
            'nested' => ['a' => 1],
            'object' => new \stdClass(),
            'null' => null,
            'bool' => false,
            'int' => 0,
        ]);

        $this->assertTrue(true);
    }
}
