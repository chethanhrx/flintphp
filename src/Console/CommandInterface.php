<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Console;

/**
 * Contract for all console commands.
 */
interface CommandInterface
{
    /**
     * The name of the command.
     */
    public function name(): string;

    /**
     * A brief description of the command.
     */
    public function description(): string;

    /**
     * Execute the command logic.
     *
     * @param InputInterface $input The console input.
     * @param OutputInterface $output The console output.
     * @return int The exit code. 0 for success.
     */
    public function execute(InputInterface $input, OutputInterface $output): int;
}
