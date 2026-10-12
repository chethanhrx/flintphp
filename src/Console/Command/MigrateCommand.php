<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Console\Command;

use FlintPHP\Framework\Console\CommandInterface;
use FlintPHP\Framework\Console\InputInterface;
use FlintPHP\Framework\Console\OutputInterface;
use FlintPHP\Framework\Database\Migration\MigrationInterface;
use FlintPHP\Framework\Database\Migration\Migrator;

/**
 * Run pending migrations. Register explicitly in your app console:
 *
 *   $console->register(new MigrateCommand($migrator, [$m1, $m2]));
 */
final class MigrateCommand implements CommandInterface
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
        return 'migrate';
    }

    public function description(): string
    {
        return 'Run pending database migrations';
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $ran = $this->migrator->migrate($this->migrations);

        if ($ran === []) {
            $output->writeLn('Nothing to migrate.');

            return 0;
        }

        foreach ($ran as $id) {
            $output->writeLn('Migrated: ' . $id);
        }

        $output->writeLn(sprintf('Done. %d migration(s).', count($ran)));

        return 0;
    }
}
