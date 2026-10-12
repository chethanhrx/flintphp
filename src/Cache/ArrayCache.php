<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Cache;

use FlintPHP\Framework\Cache\Exception\InvalidArgumentException;
use FlintPHP\Framework\Cache\Exception\InvalidCacheValueException;
use JsonException;

final class ArrayCache implements CacheInterface, AtomicCounterInterface
{
    private array $data = [];

    public function get(string $key, mixed $default = null): mixed
    {
        $this->validateKey($key);

        if (!$this->has($key)) {
            return $default;
        }

        try {
            return json_decode($this->data[$key]['value'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            unset($this->data[$key]);
            return $default;
        }
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $this->validateKey($key);

        if ($ttl !== null && $ttl <= 0) {
            $this->delete($key);
            return true;
        }

        try {
            $payload = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $e) {
            throw new InvalidCacheValueException('Cannot serialize value to JSON for cache: ' . $e->getMessage(), 0, $e);
        }

        $expiresAt = $ttl === null ? null : time() + $ttl;

        $this->data[$key] = [
            'value' => $payload,
            'expires_at' => $expiresAt,
        ];

        return true;
    }

    public function delete(string $key): bool
    {
        $this->validateKey($key);
        unset($this->data[$key]);
        return true;
    }

    public function clear(): bool
    {
        $this->data = [];
        return true;
    }

    public function has(string $key): bool
    {
        $this->validateKey($key);

        if (!isset($this->data[$key])) {
            return false;
        }

        $expiresAt = $this->data[$key]['expires_at'];

        if ($expiresAt !== null && $expiresAt < time()) {
            unset($this->data[$key]);
            return false;
        }

        return true;
    }

    public function increment(string $key, int $step = 1, ?int $ttl = null): int
    {
        $this->validateKey($key);

        if ($step < 1) {
            throw new InvalidArgumentException('Increment step must be at least 1.');
        }

        if ($ttl !== null && $ttl <= 0) {
            throw new InvalidArgumentException('Initial TTL must be greater than 0.');
        }

        if (isset($this->data[$key])) {
            $expiresAt = $this->data[$key]['expires_at'];
            if ($expiresAt !== null && $expiresAt < time()) {
                unset($this->data[$key]);
            }
        }

        if (!isset($this->data[$key])) {
            $expiresAt = $ttl === null ? null : time() + $ttl;
            $this->data[$key] = [
                'value' => json_encode($step, JSON_THROW_ON_ERROR),
                'expires_at' => $expiresAt,
            ];

            return $step;
        }

        try {
            $current = json_decode($this->data[$key]['value'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $current = 0;
        }

        $currentInt = is_int($current) ? $current : (is_numeric($current) ? (int) $current : 0);
        $newValue = $currentInt + $step;

        // Preserve existing expiration
        $this->data[$key]['value'] = json_encode($newValue, JSON_THROW_ON_ERROR);

        return $newValue;
    }

    private function validateKey(string $key): void
    {
        if (!preg_match('/^[a-zA-Z0-9_.]{1,64}$/', $key)) {
            throw new InvalidArgumentException(sprintf('Invalid cache key: "%s"', $key));
        }
    }
}

