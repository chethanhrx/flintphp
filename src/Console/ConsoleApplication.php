<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Console;

use InvalidArgumentException;
use RuntimeException;

/**
 * Registry and dispatcher for console commands.
 */
final class ConsoleApplication
{
    /**
     * @var array<string, CommandInterface>
     */
    private array $commands = [];

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
     * @return int The exit code of the command (or 1 if the command was not found).
     */
    public function run(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getCommandName();

        if ($name === null) {
            $output->writeErrorLn('No command provided.');
            return 1;
        }

        $command = $this->commands[$name] ?? null;

        if ($command === null) {
            $output->writeErrorLn(sprintf('Command "%s" not found.', $name));
            return 1;
        }

        return $command->execute($input, $output);
    }
}
