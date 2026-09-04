<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Console;

/**
 * Contract for the input provided to the console application.
 */
interface InputInterface
{
    /**
     * Get the name of the command being invoked.
     *
     * @return string|null The command name, or null if none provided.
     */
    public function getCommandName(): ?string;

    /**
     * Get a positional argument after the command name.
     *
     * Index 0 is the first argument *after* the command name.
     *
     * @param int $index The 0-based index of the argument.
     * @return string|null The argument value, or null if it doesn't exist.
     */
    public function getArgument(int $index): ?string;

    /**
     * Get all positional arguments (excluding the command name).
     *
     * @return array<int, string>
     */
    public function getArguments(): array;
}
