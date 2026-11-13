<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Console\Command;

use FlintPHP\Framework\Console\CommandInterface;
use FlintPHP\Framework\Console\InputInterface;
use FlintPHP\Framework\Console\OutputInterface;
use FlintPHP\Framework\Console\ParsedInputInterface;
use FlintPHP\Framework\Database\Migration\MigrationInterface;
use FlintPHP\Framework\Database\Migration\Migrator;

/**
 * Roll back the last N migrations (default 1).
 *
 *   $console->register(new MigrateRollbackCommand($migrator, $migrations));
 *
 * Options (ParsedInput): --steps=N
 */
final class MigrateRollbackCommand implements CommandInterface
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
        return 'migrate:rollback';
    }

    public function description(): string
    {
        return 'Roll back the last database migration(s)';
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $steps = 1;

        if ($input instanceof ParsedInputInterface) {
            $raw = $input->option('steps');
            if ($raw !== null && $raw !== '') {
                if (!ctype_digit($raw) || (int) $raw < 1) {
                    $output->writeLn('Option --steps must be a positive integer.');

                    return 1;
                }
                $steps = (int) $raw;
            }
        }

        $rolled = $this->migrator->rollback($this->migrations, $steps);

        if ($rolled === []) {
            $output->writeLn('Nothing to roll back.');

            return 0;
        }

        foreach ($rolled as $id) {
            $output->writeLn('Rolled back: ' . $id);
        }

        $output->writeLn(sprintf('Done. %d migration(s).', count($rolled)));

        return 0;
    }
}
