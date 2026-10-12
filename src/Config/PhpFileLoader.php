<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Config;

use FlintPHP\Framework\Config\Exception\ConfigurationException;

/**
 * Explicit PHP config file loader.
 *
 * Loads `*.php` files from a directory. Each file must return an array.
 * The filename (without extension) becomes the top-level config key.
 *
 * Example:
 *   config/app.php    -> ['app' => [...]]
 *   config/database.php -> ['database' => [...]]
 *
 * No recursive directory scanning beyond the given path. No magic merge
 * of environment variables — callers combine Env + this loader themselves.
 */
final class PhpFileLoader
{
    /**
     * @return array<string, mixed>
     *
     * @throws ConfigurationException
     */
    public static function loadDirectory(string $directory): array
    {
        if (!is_dir($directory) || !is_readable($directory)) {
            throw new ConfigurationException(
                sprintf('Config directory is not readable: %s', $directory)
            );
        }

        $config = [];
        $files = glob(rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files);

        foreach ($files as $file) {
            $key = basename($file, '.php');

            if (!preg_match('/\A[a-zA-Z0-9_-]+\z/', $key)) {
                continue;
            }

            /** @var mixed $data */
            $data = require $file;

            if (!is_array($data)) {
                throw new ConfigurationException(
                    sprintf('Config file "%s" must return an array.', $file)
                );
            }

            $config[$key] = $data;
        }

        return $config;
    }
}
