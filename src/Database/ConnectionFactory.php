<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Database;

use InvalidArgumentException;
use SensitiveParameter;

final class ConnectionFactory
{
    /**
     * Create a new ConnectionInterface instance from a configuration array.
     *
     * @param array<string, mixed> $config
     */
    public static function make(array $config): ConnectionInterface
    {
        $driver = $config['driver'] ?? null;

        if ($driver === null) {
            throw new InvalidArgumentException('A driver must be specified.');
        }

        $dsn = self::buildDsn($config);

        $username = (string) ($config['username'] ?? '');
        $password = (string) ($config['password'] ?? '');
        $options  = (array) ($config['options'] ?? []);

        return new PdoConnection($dsn, $username, $password, $options);
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function buildDsn(array $config): string
    {
        $driver = $config['driver'];

        if ($driver === 'sqlite') {
            $database = $config['database'] ?? ':memory:';
            return "sqlite:{$database}";
        }

        if ($driver === 'mysql' || $driver === 'pgsql') {
            return self::buildServerDsn($driver, $config);
        }

        throw new InvalidArgumentException(sprintf('Unsupported database driver: %s', $driver));
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function buildServerDsn(string $driver, array $config): string
    {
        $parts = [];

        if (isset($config['host'])) {
            $parts[] = "host={$config['host']}";
        }

        if (isset($config['port'])) {
            $parts[] = "port={$config['port']}";
        }

        if (isset($config['database'])) {
            $parts[] = "dbname={$config['database']}";
        }

        if ($driver === 'mysql' && isset($config['charset'])) {
            $parts[] = "charset={$config['charset']}";
        }

        return $driver . ':' . implode(';', $parts);
    }
}
