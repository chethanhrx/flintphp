<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Console\Command;

use FlintPHP\Framework\Console\CommandInterface;
use FlintPHP\Framework\Console\InputInterface;
use FlintPHP\Framework\Console\OutputInterface;
use FlintPHP\Framework\Database\Migration\MigrationInterface;
use FlintPHP\Framework\Database\Migration\Migrator;

/**
 * Show applied vs pending migrations.
 *
 *   $console->register(new MigrateStatusCommand($migrator, $migrations));
 */
final class MigrateStatusCommand implements CommandInterface
{
    /**
     * @param list<MigrationInterface> $migrations
     */
    public function __construct(
        private readonly Migrator $migrator,
        private readonly array $migrations,
    ) {
    }

    public function name(): string
    {
        return 'migrate:status';
    }

    public function description(): string
    {
        return 'Show which migrations are applied or pending';
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $lines = $this->migrator->status($this->migrations);

        if ($lines === []) {
            $output->writeLn('No migrations registered.');

            return 0;
        }

        foreach ($lines as $line) {
            $output->writeLn($line);
        }

        return 0;
    }
}
