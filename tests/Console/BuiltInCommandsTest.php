<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Console;

use FlintPHP\Framework\Console\Command\HelpCommand;
use FlintPHP\Framework\Console\Command\ListCommand;
use FlintPHP\Framework\Console\CommandInterface;
use FlintPHP\Framework\Console\ConsoleApplication;
use FlintPHP\Framework\Console\ConsoleOutput;
use FlintPHP\Framework\Console\Input;
use FlintPHP\Framework\Console\InputInterface;
use FlintPHP\Framework\Console\OutputInterface;
use FlintPHP\Framework\Console\ParsedInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ListCommand::class)]
#[CoversClass(HelpCommand::class)]
#[CoversClass(ConsoleApplication::class)]
final class BuiltInCommandsTest extends TestCase
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

    private function stdout(): string
    {
        rewind($this->stdout);

        return (string) stream_get_contents($this->stdout);
    }

    private function stderr(): string
    {
        rewind($this->stderr);

        return (string) stream_get_contents($this->stderr);
    }

    #[Test]
    public function built_ins_are_registered_by_default(): void
    {
        $app = new ConsoleApplication();

        $this->assertInstanceOf(ListCommand::class, $app->command('list'));
        $this->assertInstanceOf(HelpCommand::class, $app->command('help'));
    }

    #[Test]
    public function bare_registry_has_no_built_ins(): void
    {
        $app = new ConsoleApplication(false);

        $this->assertNull($app->command('list'));
        $this->assertNull($app->command('help'));
        $this->assertSame([], $app->commands());

        // And "list" is now just an unknown command.
        $exit = $app->run(new Input(['bin/flint', 'list']), $this->output);
        $this->assertSame(1, $exit);
        $this->assertSame('Command "list" not found.' . PHP_EOL, $this->stderr());
    }

    #[Test]
    public function list_shows_registered_commands_in_deterministic_order(): void
    {
        $app = new ConsoleApplication(); // built-ins enabled: list/help exist
        $app->register($this->command('zeta:cmd', 'Last'));
        $app->register($this->command('alpha', 'First'));

        $exit = $app->run(new Input(['bin/flint', 'list']), $this->output);

        $this->assertSame(0, $exit);
        $out = $this->stdout();
        $this->assertStringContainsString('alpha', $out);
        $this->assertStringContainsString('First', $out);
        $this->assertStringContainsString('zeta:cmd', $out);
        $this->assertStringContainsString('Last', $out);
        // Deterministic alphabetical order regardless of registration order.
        $this->assertLessThan(strpos($out, 'zeta:cmd'), (int) strpos($out, 'alpha'));
    }

    #[Test]
    public function list_with_an_empty_registry_is_quiet_and_successful(): void
    {
        // Bare registry = zero commands. ListCommand must render nothing and
        // succeed (deterministic empty-state behavior).
        $emptyApp = new ConsoleApplication(false);
        $listCommand = new ListCommand($emptyApp);

        $exit = $listCommand->execute(new Input(['bin/flint', 'list']), $this->output);

        $this->assertSame(0, $exit);
        $this->assertSame('', $this->stdout());
        $this->assertSame('', $this->stderr());
    }

    #[Test]
    public function help_without_arguments_shows_usage_and_commands(): void
    {
        $app = new ConsoleApplication(); // built-ins enabled
        $app->register($this->command('demo', 'Demo command'));

        $exit = $app->run(new Input(['bin/flint', 'help']), $this->output);

        $this->assertSame(0, $exit);
        $out = $this->stdout();
        $this->assertStringContainsString('Usage: flint', $out);
        $this->assertStringContainsString('demo', $out);
        $this->assertStringContainsString('Demo command', $out);
    }

    #[Test]
    public function help_for_a_specific_command(): void
    {
        $app = new ConsoleApplication(); // built-ins enabled
        $app->register($this->command('demo', 'Demo command'));

        $exit = $app->run(new Input(['bin/flint', 'help', 'demo']), $this->output);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('demo', $this->stdout());
        $this->assertStringContainsString('Demo command', $this->stdout());
        $this->assertSame('', $this->stderr());
    }

    #[Test]
    public function help_for_unknown_command_fails_clearly(): void
    {
        $app = new ConsoleApplication();

        $exit = $app->run(new Input(['bin/flint', 'help', 'ghost']), $this->output);

        $this->assertSame(1, $exit);
        $this->assertSame('Command "ghost" not found.' . PHP_EOL, $this->stderr());
    }

    #[Test]
    public function literal_help_option_routes_to_the_help_command(): void
    {
        $app = new ConsoleApplication();

        $exit = $app->run(new Input(['bin/flint', '--help']), $this->output);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Usage: flint', $this->stdout());
    }

    #[Test]
    public function literal_help_option_is_not_active_in_bare_registry(): void
    {
        $app = new ConsoleApplication(false);

        $exit = $app->run(new Input(['bin/flint', '--help']), $this->output);

        $this->assertSame(1, $exit);
        $this->assertSame('Command "--help" not found.' . PHP_EOL, $this->stderr());
    }

    #[Test]
    public function built_in_names_cannot_be_shadowed(): void
    {
        $app = new ConsoleApplication();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Command "list" is already registered.');

        $app->register($this->command('list', 'Impostor'));
    }

    #[Test]
    public function commands_are_reusable_across_repeated_runs(): void
    {
        $app = new ConsoleApplication();
        $counter = new class implements CommandInterface {
            public int $runs = 0;

            public function name(): string
            {
                return 'count';
            }

            public function description(): string
            {
                return 'Counts runs';
            }

            public function execute(InputInterface $input, OutputInterface $output): int
            {
                $this->runs++;

                return 0;
            }
        };
        $app->register($counter);

        $app->run(new Input(['bin/flint', 'count']), $this->output);
        $app->run(new Input(['bin/flint', 'count']), $this->output);
        $app->run(new Input(['bin/flint', 'count']), $this->output);

        $this->assertSame(3, $counter->runs);
    }

    #[Test]
    public function parsed_input_flows_through_dispatch_to_builtins(): void
    {
        $app = new ConsoleApplication(); // built-ins enabled
        $app->register($this->command('demo', 'Demo'));

        // Option tokens are classified, while dispatch still uses the raw
        // command name token.
        $exit = $app->run(new ParsedInput(['bin/flint', 'help', 'demo', '--section=all']), $this->output);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Demo', $this->stdout());
    }

    private function command(string $name, string $description): CommandInterface
    {
        return new class($name, $description) implements CommandInterface {
            public function __construct(
                private readonly string $name,
                private readonly string $description,
            ) {
            }

            public function name(): string
            {
                return $this->name;
            }

            public function description(): string
            {
                return $this->description;
            }

            public function execute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }
        };
    }
}
