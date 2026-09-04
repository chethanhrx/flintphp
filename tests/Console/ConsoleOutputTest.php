<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Console;

use FlintPHP\Framework\Console\ConsoleOutput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConsoleOutput::class)]
final class ConsoleOutputTest extends TestCase
{
    /** @var resource */
    private $stdout;
    /** @var resource */
    private $stderr;
    private ConsoleOutput $output;

    protected function setUp(): void
    {
        $this->stdout = fopen('php://memory', 'w+');
        $this->stderr = fopen('php://memory', 'w+');
        $this->output = new ConsoleOutput($this->stdout, $this->stderr);
    }

    protected function tearDown(): void
    {
        fclose($this->stdout);
        fclose($this->stderr);
    }

    #[Test]
    public function it_writes_to_stdout(): void
    {
        $this->output->write('Hello');
        $this->output->writeLn(' World');

        rewind($this->stdout);
        $this->assertSame('Hello World' . PHP_EOL, stream_get_contents($this->stdout));
        
        rewind($this->stderr);
        $this->assertSame('', stream_get_contents($this->stderr));
    }

    #[Test]
    public function it_writes_to_stderr(): void
    {
        $this->output->writeError('Error');
        $this->output->writeErrorLn(' message');

        rewind($this->stderr);
        $this->assertSame('Error message' . PHP_EOL, stream_get_contents($this->stderr));
        
        rewind($this->stdout);
        $this->assertSame('', stream_get_contents($this->stdout));
    }

    #[Test]
    public function it_rejects_invalid_stdout_stream(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('STDOUT must be a valid stream resource.');

        new ConsoleOutput('not a resource', STDERR);
    }

    #[Test]
    public function it_rejects_invalid_stderr_stream(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('STDERR must be a valid stream resource.');

        new ConsoleOutput(STDOUT, 'not a resource');
    }
}
