<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Console;

use FlintPHP\Framework\Console\Command\HelpCommand;
use FlintPHP\Framework\Console\Command\ListCommand;
use InvalidArgumentException;
use RuntimeException;

/**
 * Registry and dispatcher for console commands.
 *
 * Commands must be explicitly registered via register(). No filesystem
 * scanning, no class discovery, no global state. By default two built-in
 * commands ("list" and "help") are registered; pass false to the constructor
 * to obtain the bare registry.
 */
final class ConsoleApplication implements CommandCollectionInterface
{
    /**
     * @var array<string, CommandInterface>
     */
    private array $commands = [];

    private readonly bool $builtInsRegistered;

    /**
     * @param bool $registerBuiltIns When true (default), registers the
     *                               built-in "list" and "help" commands.
     *                               Both are ordinary CommandInterface
     *                               implementations backed by this instance's
     *                               read-only collection view — no magic.
     */
    public function __construct(bool $registerBuiltIns = true)
    {
        $this->builtInsRegistered = $registerBuiltIns;

        if ($registerBuiltIns) {
            $this->register(new ListCommand($this));
            $this->register(new HelpCommand($this));
        }
    }

    /**
     * Register a new command with the application.
     *
     * @throws RuntimeException If a command with the same name is already registered.
     * @throws InvalidArgumentException If the command name is invalid.
     */
    public function register(CommandInterface $command): void
    {
        $name = $command->name();

        if (preg_match('/^[a-z0-9]+(?:[:\-][a-z0-9]+)*$/', $name) !== 1) {
            throw new InvalidArgumentException(
                sprintf('Invalid command name "%s". Only lowercase alphanumeric characters, colons, and hyphens are allowed.', $name)
            );
        }

        if (isset($this->commands[$name])) {
            throw new RuntimeException(sprintf('Command "%s" is already registered.', $name));
        }

        $this->commands[$name] = $command;
    }

    /**
     * Run the console application with the given input and output.
     *
     * The first token after the script name is dispatched verbatim. When
     * built-ins are enabled, a literal "--help" token is routed to the help
     * command. Dispatched tokens that collide with nothing registered produce
     * the standard "not found" error with exit code 1.
     *
     * @return int The exit code of the command (or 1 if the command was not found).
     */
    public function run(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getCommandName();

        if ($name === null) {
            $output->writeErrorLn('No command provided.');
            return 1;
        }

        if ($this->builtInsRegistered && $name === '--help') {
            return $this->commands[HelpCommand::NAME]->execute($input, $output);
        }

        $command = $this->commands[$name] ?? null;

        if ($command === null) {
            $output->writeErrorLn(sprintf('Command "%s" not found.', $name));
            return 1;
        }

        return $command->execute($input, $output);
    }

    /**
     * Read-only view for the built-in list/help commands.
     *
     * @return array<string, CommandInterface>
     */
    public function commands(): array
    {
        return $this->commands;
    }

    public function command(string $name): ?CommandInterface
    {
        return $this->commands[$name] ?? null;
    }
}
