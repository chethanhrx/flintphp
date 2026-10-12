<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Database\Migration;

use FlintPHP\Framework\Database\ConnectionInterface;

/**
 * A single schema change. Implementations must be idempotent only via Migrator tracking.
 */
interface MigrationInterface
{
    /** Unique version id, e.g. 20261012120000_create_users */
    public function id(): string;

    public function up(ConnectionInterface $connection): void;

    public function down(ConnectionInterface $connection): void;
}
