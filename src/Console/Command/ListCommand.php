<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Console\Command;

use FlintPHP\Framework\Console\CommandCollectionInterface;
use FlintPHP\Framework\Console\CommandInterface;
use FlintPHP\Framework\Console\InputInterface;
use FlintPHP\Framework\Console\OutputInterface;

/**
 * Built-in command: lists all explicitly registered commands.
 *
 * Generated exclusively from the registry — no filesystem scanning, no
 * reflection discovery. Renders nothing when no commands are registered
 * (deterministic, quiet, exit 0).
 */
final class ListCommand implements CommandInterface
{
    public const NAME = 'list';

    public function __construct(
        private readonly CommandCollectionInterface $commands,
    ) {
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function description(): string
    {
        return 'List all registered commands';
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $commands = $this->commands->commands();
        ksort($commands, SORT_STRING);

        foreach ($commands as $command) {
            $output->writeLn(sprintf('%-28s %s', $command->name(), $command->description()));
        }

        return 0;
    }
}
