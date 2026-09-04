<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Console;

use FlintPHP\Framework\Console\CommandInterface;
use FlintPHP\Framework\Console\ConsoleApplication;
use FlintPHP\Framework\Console\ConsoleOutput;
use FlintPHP\Framework\Console\Input;
use FlintPHP\Framework\Console\InputInterface;
use FlintPHP\Framework\Console\OutputInterface;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ConsoleApplication::class)]
final class ConsoleApplicationTest extends TestCase
{
    private ConsoleApplication $app;

    protected function setUp(): void
    {
        $this->app = new ConsoleApplication();
    }

    private function createOutputBuffer(): array
    {
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');
        return [$stdout, $stderr, new ConsoleOutput($stdout, $stderr)];
    }

    private function getStreamContent($stream): string
    {
        rewind($stream);
        return stream_get_contents($stream);
    }

    #[Test]
    public function it_registers_and_executes_a_command(): void
    {
        $command = new class implements CommandInterface {
            public bool $executed = false;
            
            public function name(): string { return 'test:command'; }
            public function description(): string { return 'A test command'; }
            public function execute(InputInterface $input, OutputInterface $output): int {
                $this->executed = true;
                $output->writeLn('Success');
                return 0;
            }
        };

        $this->app->register($command);

        $input = new Input(['bin/console', 'test:command']);
        [$stdout, $stderr, $output] = $this->createOutputBuffer();

        $exitCode = $this->app->run($input, $output);

        $this->assertSame(0, $exitCode);
        $this->assertTrue($command->executed);
        $this->assertSame('Success' . PHP_EOL, $this->getStreamContent($stdout));
        $this->assertSame('', $this->getStreamContent($stderr));
    }

    #[Test]
    public function it_rejects_duplicate_commands(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $command->method('name')->willReturn('duplicate:cmd');

        $this->app->register($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Command "duplicate:cmd" is already registered.');

        $this->app->register($command);
    }

    #[Test]
    public function it_rejects_invalid_command_names(): void
    {
        $invalidNames = [
            'invalid name',
            ':',
            'make:',
            ':controller',
            'make::controller',
            'make--controller',
            'Make:Controller',
            '-v',
            'v-'
        ];

        foreach ($invalidNames as $invalidName) {
            $command = $this->createMock(CommandInterface::class);
            $command->method('name')->willReturn($invalidName);

            try {
                $this->app->register($command);
                $this->fail(sprintf('Expected InvalidArgumentException for name "%s".', $invalidName));
            } catch (InvalidArgumentException $e) {
                $this->assertSame(
                    sprintf('Invalid command name "%s". Only lowercase alphanumeric characters, colons, and hyphens are allowed.', $invalidName),
                    $e->getMessage()
                );
            }
        }
    }

    #[Test]
    public function it_returns_1_and_writes_to_stderr_for_unknown_command(): void
    {
        $input = new Input(['bin/console', 'unknown:cmd']);
        [$stdout, $stderr, $output] = $this->createOutputBuffer();

        $exitCode = $this->app->run($input, $output);

        $this->assertSame(1, $exitCode);
        $this->assertSame('', $this->getStreamContent($stdout));
        $this->assertSame('Command "unknown:cmd" not found.' . PHP_EOL, $this->getStreamContent($stderr));
    }

    #[Test]
    public function it_returns_1_and_writes_to_stderr_when_no_command_provided(): void
    {
        $input = new Input(['bin/console']);
        [$stdout, $stderr, $output] = $this->createOutputBuffer();

        $exitCode = $this->app->run($input, $output);

        $this->assertSame(1, $exitCode);
        $this->assertSame('', $this->getStreamContent($stdout));
        $this->assertSame('No command provided.' . PHP_EOL, $this->getStreamContent($stderr));
    }

    #[Test]
    public function command_exceptions_propagate_out_naturally(): void
    {
        $command = new class implements CommandInterface {
            public function name(): string { return 'fail:cmd'; }
            public function description(): string { return 'Fails'; }
            public function execute(InputInterface $input, OutputInterface $output): int {
                throw new RuntimeException('Command crashed');
            }
        };

        $this->app->register($command);

        $input = new Input(['bin/console', 'fail:cmd']);
        [$stdout, $stderr, $output] = $this->createOutputBuffer();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Command crashed');

        $this->app->run($input, $output);
    }
}
