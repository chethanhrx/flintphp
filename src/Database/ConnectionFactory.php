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
     * Configuration is validated eagerly and loudly: malformed values throw
     * an InvalidArgumentException naming the offending key and the value's
     * TYPE (never the value itself, which may be a credential).
     *
     * @param array<string, mixed> $config
     */
    public static function make(array $config): ConnectionInterface
    {
        $driver = $config['driver'] ?? null;

        if (!is_string($driver) || $driver === '') {
            throw new InvalidArgumentException('A driver must be specified.');
        }

        $dsn = self::buildDsn($driver, $config);

        $username = self::optionalString($config, 'username');
        $password = self::optionalString($config, 'password');
        $options  = self::optionsArray($config);

        return new PdoConnection($dsn, $username, $password, $options);
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function buildDsn(string $driver, array $config): string
    {
        if ($driver === 'sqlite') {
            $database = $config['database'] ?? ':memory:';

            if (!is_string($database)) {
                throw new InvalidArgumentException(sprintf(
                    'Database configuration "database" must be a string, got %s.',
                    get_debug_type($database)
                ));
            }

            return 'sqlite:' . $database;
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
            $parts[] = 'host=' . self::dsnValue($config, 'host');
        }

        if (isset($config['port'])) {
            $parts[] = 'port=' . self::dsnPort($config);
        }

        if (isset($config['database'])) {
            $parts[] = 'dbname=' . self::dsnValue($config, 'database');
        }

        if ($driver === 'mysql' && isset($config['charset'])) {
            $parts[] = 'charset=' . self::dsnValue($config, 'charset');
        }

        return $driver . ':' . implode(';', $parts);
    }

    /**
     * Validate a string-or-integer DSN component.
     *
     * @param array<string, mixed> $config
     */
    private static function dsnValue(array $config, string $key): string
    {
        $value = $config[$key];

        if (!is_string($value) && !is_int($value)) {
            throw new InvalidArgumentException(sprintf(
                'Database configuration "%s" must be a string or integer, got %s.',
                $key,
                get_debug_type($value)
            ));
        }

        return (string) $value;
    }

    /**
     * Validate the DSN port component.
     *
     * @param array<string, mixed> $config
     */
    private static function dsnPort(array $config): string
    {
        $value = $config['port'];

        if (!is_int($value) && !(is_string($value) && preg_match('/^\d+$/', $value) === 1)) {
            throw new InvalidArgumentException(sprintf(
                'Database configuration "port" must be an integer or numeric string, got %s.',
                get_debug_type($value)
            ));
        }

        return (string) $value;
    }

    /**
     * Validate an optional string configuration value (e.g. credentials).
     *
     * @param array<string, mixed> $config
     */
    private static function optionalString(array $config, string $key): string
    {
        $value = $config[$key] ?? null;

        if ($value === null) {
            return '';
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException(sprintf(
                'Database configuration "%s" must be a string or null, got %s.',
                $key,
                get_debug_type($value)
            ));
        }

        return $value;
    }

    /**
     * Validate the PDO options array.
     *
     * @param array<string, mixed> $config
     * @return array<int|string, mixed>
     */
    private static function optionsArray(array $config): array
    {
        $options = $config['options'] ?? [];

        if (!is_array($options)) {
            throw new InvalidArgumentException(sprintf(
                'Database configuration "options" must be an array, got %s.',
                get_debug_type($options)
            ));
        }

        return $options;
    }
}
