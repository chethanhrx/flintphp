<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Console\Command;

use FlintPHP\Framework\Console\CommandCollectionInterface;
use FlintPHP\Framework\Console\CommandInterface;
use FlintPHP\Framework\Console\InputInterface;
use FlintPHP\Framework\Console\OutputInterface;

/**
 * Built-in command: shows general help or per-command help.
 *
 * help          -> lists registered commands (same as `list`)
 * help <name>   -> shows the registered command's name + description
 *
 * Generated exclusively from the registry. Unknown names produce a clear
 * error on stderr with exit code 1.
 */
final class HelpCommand implements CommandInterface
{
    public const NAME = 'help';

    public function __construct(
        private readonly CommandCollectionInterface $commands,
    ) {
    }

    public function name(): string
    {
        return self::NAME;
    }

    /**
     * UX behavior:
     * - `help` (no arguments): usage hint plus the command listing.
     * - `help <name>`: the registered command's name and description.
     */
    public function description(): string
    {
        return 'Show help for a command, or list all commands';
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $target = $input->getArgument(0);

        if ($target === null) {
            $output->writeLn('Usage: flint <command> [arguments] [--option=value] [--] [args...]');
            $output->writeLn('');
            $output->writeLn('Commands:');

            $commands = $this->commands->commands();
            ksort($commands, SORT_STRING);

            foreach ($commands as $command) {
                $output->writeLn(sprintf('  %-26s %s', $command->name(), $command->description()));
            }

            return 0;
        }

        $command = $this->commands->command($target);

        if ($command === null) {
            $output->writeErrorLn(sprintf('Command "%s" not found.', $target));
            return 1;
        }

        $output->writeLn($command->name());
        $output->writeLn('    ' . $command->description());

        return 0;
    }
}
