<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Console;

/**
 * Represents the input provided to the console application.
 */
final readonly class Input implements InputInterface
{
    /**
     * @var array<int, string>
     */
    private array $arguments;

    public function __construct(array $argv)
    {
        // $argv[0] is typically the script name (e.g. 'bin/console').
        // We slice off the first element to only keep the actual arguments provided.
        $this->arguments = array_values(array_slice($argv, 1));
    }

    /**
     * Get the name of the command being invoked.
     */
    public function getCommandName(): ?string
    {
        return $this->arguments[0] ?? null;
    }

    /**
     * Get a positional argument after the command name.
     *
     * Index 0 is the first argument *after* the command name.
     */
    public function getArgument(int $index): ?string
    {
        // Shift by 1 to skip the command name itself
        return $this->arguments[$index + 1] ?? null;
    }

    /**
     * Get all positional arguments (excluding the command name).
     *
     * @return array<int, string>
     */
    public function getArguments(): array
    {
        return array_values(array_slice($this->arguments, 1));
    }
}
