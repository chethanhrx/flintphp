<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Config;

use FlintPHP\Framework\Config\Exception\ConfigurationException;

/**
 * Explicit .env file loader.
 *
 * Loads KEY=VALUE pairs from a file into a returned array. Does not
 * automatically inject values into application classes. Callers decide
 * how (and whether) to merge results into ConfigRepository or $_ENV.
 *
 * Rules:
 * - Lines starting with # are comments
 * - Empty lines are ignored
 * - Values may be optionally double- or single-quoted
 * - Existing keys in $existing are not overwritten (first wins)
 * - No variable interpolation, no shell execution
 */
final class Env
{
    /**
     * Load environment variables from a file.
     *
     * @param string               $path     Absolute path to the .env file
     * @param array<string, string> $existing Values that must not be overwritten
     *
     * @return array<string, string>
     *
     * @throws ConfigurationException If the file cannot be read
     */
    public static function load(string $path, array $existing = []): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new ConfigurationException(
                sprintf('Environment file is not readable: %s', $path)
            );
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new ConfigurationException(
                sprintf('Failed to read environment file: %s', $path)
            );
        }

        $result = $existing;

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $eq = strpos($line, '=');
            if ($eq === false) {
                continue;
            }

            $key = trim(substr($line, 0, $eq));
            $value = trim(substr($line, $eq + 1));

            if ($key === '' || !preg_match('/\A[A-Z_][A-Z0-9_]*\z/', $key)) {
                continue;
            }

            if (array_key_exists($key, $result)) {
                continue;
            }

            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * Read a single value from an already-loaded map or PHP environment.
     */
    public static function get(string $key, ?string $default = null, ?array $map = null): ?string
    {
        if ($map !== null && array_key_exists($key, $map)) {
            return $map[$key];
        }

        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false) {
            return $default;
        }

        return (string) $value;
    }
}
