<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Config;

use FlintPHP\Framework\Config\Contract\ConfigRepositoryInterface;
use FlintPHP\Framework\Config\Exception\ConfigurationException;

final class ConfigRepository implements ConfigRepositoryInterface
{
    private const KEY_PATTERN = '/\A[a-zA-Z0-9_-]+(?:\.[a-zA-Z0-9_-]+)*\z/';
    private const MAX_KEY_LENGTH = 128;

    /**
     * @param array<string, mixed> $configuration
     */
    public function __construct(
        private readonly array $configuration
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->validateKey($key);

        $segments = explode('.', $key);
        $array = $this->configuration;

        foreach ($segments as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }

            $array = $array[$segment];
        }

        return $array;
    }

    public function has(string $key): bool
    {
        $this->validateKey($key);

        $segments = explode('.', $key);
        $array = $this->configuration;

        foreach ($segments as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return false;
            }

            $array = $array[$segment];
        }

        return true;
    }

    public function all(): array
    {
        return $this->configuration;
    }

    /**
     * @throws ConfigurationException
     */
    private function validateKey(string $key): void
    {
        if (strlen($key) > self::MAX_KEY_LENGTH) {
            throw new ConfigurationException(
                sprintf('Configuration key exceeds maximum length of %d characters.', self::MAX_KEY_LENGTH)
            );
        }

        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw new ConfigurationException(
                sprintf('Invalid configuration key format: "%s".', $key)
            );
        }
    }
}
