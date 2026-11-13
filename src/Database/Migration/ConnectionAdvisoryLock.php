<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Database\Migration;

use FlintPHP\Framework\Database\ConnectionInterface;
use InvalidArgumentException;
use PDO;

/**
 * Driver-aware advisory lock backed by the same PDO connection used for migrations.
 *
 * - MySQL / MariaDB: GET_LOCK / RELEASE_LOCK (named, connection-scoped)
 * - PostgreSQL: pg_try_advisory_lock / pg_advisory_unlock (two-int key from lock name)
 * - SQLite: no-op success (no server-side advisory locks)
 */
final class ConnectionAdvisoryLock implements AdvisoryLockInterface
{
    public function __construct(
        private readonly ConnectionInterface $connection,
    ) {
    }

    public function acquire(string $name, int $timeoutSeconds): void
    {
        $this->assertName($name);

        if ($timeoutSeconds < 0) {
            throw new InvalidArgumentException('Lock timeout must be >= 0');
        }

        $driver = $this->driver();

        match ($driver) {
            'mysql' => $this->acquireMysql($name, $timeoutSeconds),
            'pgsql' => $this->acquirePgsql($name, $timeoutSeconds),
            'sqlite' => null, // no-op
            default => throw new MigrationLockException(sprintf(
                'Advisory locks are not supported for driver "%s".',
                $driver
            )),
        };
    }

    public function release(string $name): void
    {
        $this->assertName($name);

        $driver = $this->driver();

        match ($driver) {
            'mysql' => $this->releaseMysql($name),
            'pgsql' => $this->releasePgsql($name),
            'sqlite' => null,
            default => null,
        };
    }

    private function acquireMysql(string $name, int $timeoutSeconds): void
    {
        // GET_LOCK returns 1 on success, 0 on timeout, NULL on error.
        $result = $this->connection->fetchColumn(
            'SELECT GET_LOCK(?, ?)',
            [$name, $timeoutSeconds]
        );

        if ((int) $result === 1) {
            return;
        }

        throw new MigrationLockException(sprintf(
            'Could not acquire MySQL advisory lock "%s" within %d second(s). Another migrator may be running.',
            $name,
            $timeoutSeconds
        ));
    }

    private function releaseMysql(string $name): void
    {
        $this->connection->fetchColumn('SELECT RELEASE_LOCK(?)', [$name]);
    }

    private function acquirePgsql(string $name, int $timeoutSeconds): void
    {
        [$k1, $k2] = $this->pgsqlKeys($name);
        $deadline = microtime(true) + $timeoutSeconds;

        while (true) {
            $got = $this->connection->fetchColumn(
                'SELECT pg_try_advisory_lock(?, ?)',
                [$k1, $k2]
            );

            // PDO may return bool true, 't', or 1
            if ($got === true || $got === 1 || $got === '1' || $got === 't') {
                return;
            }

            if ($timeoutSeconds === 0 || microtime(true) >= $deadline) {
                throw new MigrationLockException(sprintf(
                    'Could not acquire PostgreSQL advisory lock "%s" within %d second(s). Another migrator may be running.',
                    $name,
                    $timeoutSeconds
                ));
            }

            usleep(100_000); // 100ms
        }
    }

    private function releasePgsql(string $name): void
    {
        [$k1, $k2] = $this->pgsqlKeys($name);
        $this->connection->fetchColumn('SELECT pg_advisory_unlock(?, ?)', [$k1, $k2]);
    }

    /**
     * Stable two-int key space for pg_advisory_lock(int, int).
     *
     * @return array{0: int, 1: int}
     */
    private function pgsqlKeys(string $name): array
    {
        $hash = hash('sha256', 'flintphp:' . $name, true);
        // Two signed 32-bit integers from the first 8 bytes
        $u1 = unpack('N', substr($hash, 0, 4))[1];
        $u2 = unpack('N', substr($hash, 4, 4))[1];

        // Convert unsigned to signed 32-bit range PHP/PDO accept as int
        $k1 = $u1 > 0x7FFFFFFF ? $u1 - 0x100000000 : $u1;
        $k2 = $u2 > 0x7FFFFFFF ? $u2 - 0x100000000 : $u2;

        return [$k1, $k2];
    }

    private function driver(): string
    {
        return (string) $this->connection->pdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    private function assertName(string $name): void
    {
        if ($name === '' || strlen($name) > 64 || preg_match('/\A[a-zA-Z0-9_\-]+\z/', $name) !== 1) {
            throw new InvalidArgumentException(
                'Advisory lock name must match [a-zA-Z0-9_-]{1,64}.'
            );
        }
    }
}
