<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Cache;

use FlintPHP\Framework\Cache\Exception\CacheStorageException;
use FlintPHP\Framework\Cache\Exception\InvalidArgumentException;
use FlintPHP\Framework\Cache\Exception\InvalidCacheValueException;
use DirectoryIterator;
use JsonException;

final class FileCache implements CacheInterface, AtomicCounterInterface
{
    public function __construct(
        private readonly string $directory
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->validateKey($key);
        
        $path = $this->path($key);

        if (!is_file($path)) {
            return $default;
        }

        $content = @file_get_contents($path);
        if ($content === false) {
            return $default;
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            @unlink($path);
            return $default;
        }

        if (!is_array($data) || !array_key_exists('expires_at', $data) || !array_key_exists('value', $data)) {
            @unlink($path);
            return $default;
        }

        if ($data['expires_at'] !== null && $data['expires_at'] < time()) {
            @unlink($path);
            return $default;
        }

        return $data['value'];
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $this->validateKey($key);

        if ($ttl !== null && $ttl <= 0) {
            $this->delete($key);
            return true;
        }

        if (!$this->ensureDirectory()) {
            return false;
        }

        $expiresAt = $ttl === null ? null : time() + $ttl;

        try {
            $payload = json_encode([
                'expires_at' => $expiresAt,
                'value' => $value,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $e) {
            throw new InvalidCacheValueException('Cannot serialize value to JSON for cache: ' . $e->getMessage(), 0, $e);
        }

        $path = $this->path($key);
        $tempPath = rtrim($this->directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . uniqid('', true) . '.tmp';

        if (@file_put_contents($tempPath, $payload) === false) {
            return false;
        }

        if (@rename($tempPath, $path) === false) {
            @unlink($tempPath);
            return false;
        }

        return true;
    }

    public function delete(string $key): bool
    {
        $this->validateKey($key);
        $path = $this->path($key);

        if (is_file($path)) {
            return @unlink($path);
        }

        return true;
    }

    public function clear(): bool
    {
        if (!is_dir($this->directory)) {
            return true;
        }

        $success = true;
        $iterator = new DirectoryIterator($this->directory);
        
        foreach ($iterator as $file) {
            if ($file->isFile() && preg_match('/^[a-f0-9]{64}\.json$/', $file->getFilename())) {
                if (!@unlink($file->getPathname())) {
                    $success = false;
                }
            }
        }

        return $success;
    }

    public function has(string $key): bool
    {
        $this->validateKey($key);
        
        $path = $this->path($key);

        if (!is_file($path)) {
            return false;
        }

        $content = @file_get_contents($path);
        if ($content === false) {
            return false;
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            @unlink($path);
            return false;
        }

        if (!is_array($data) || !array_key_exists('expires_at', $data) || !array_key_exists('value', $data)) {
            @unlink($path);
            return false;
        }

        if ($data['expires_at'] !== null && $data['expires_at'] < time()) {
            @unlink($path);
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

        if (!$this->ensureDirectory()) {
            throw new CacheStorageException(sprintf('Failed to create cache directory "%s".', $this->directory));
        }

        $path = $this->path($key);
        $fp = @fopen($path, 'c+');
        if ($fp === false) {
            throw new CacheStorageException(sprintf('Failed to open cache file "%s" for atomic increment.', $path));
        }

        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            throw new CacheStorageException(sprintf('Failed to acquire lock for cache file "%s".', $path));
        }

        try {
            rewind($fp);
            $content = stream_get_contents($fp);
            $data = null;
            if ($content !== false && $content !== '') {
                try {
                    $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    $data = null;
                }
            }

            $isExpired = false;
            if (is_array($data) && array_key_exists('expires_at', $data) && array_key_exists('value', $data)) {
                if ($data['expires_at'] !== null && $data['expires_at'] < time()) {
                    $isExpired = true;
                }
            } else {
                $isExpired = true;
            }

            if ($isExpired || $data === null) {
                $expiresAt = $ttl === null ? null : time() + $ttl;
                $newValue = $step;
            } else {
                $expiresAt = $data['expires_at'];
                $current = $data['value'];
                $currentInt = is_int($current) ? $current : (is_numeric($current) ? (int) $current : 0);
                $newValue = $currentInt + $step;
            }

            try {
                $payload = json_encode([
                    'expires_at' => $expiresAt,
                    'value' => $newValue,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            } catch (JsonException $e) {
                throw new InvalidCacheValueException('Cannot serialize value to JSON for cache: ' . $e->getMessage(), 0, $e);
            }

            ftruncate($fp, 0);
            rewind($fp);
            $written = fwrite($fp, $payload);
            if ($written === false) {
                throw new CacheStorageException(sprintf('Failed to write to cache file "%s".', $path));
            }
            fflush($fp);

            return $newValue;
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    private function validateKey(string $key): void
    {
        if (!preg_match('/^[a-zA-Z0-9_.]{1,64}$/', $key)) {
            throw new InvalidArgumentException(sprintf('Invalid cache key: "%s"', $key));
        }
    }

    private function path(string $key): string
    {
        return rtrim($this->directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.json';
    }

    private function ensureDirectory(): bool
    {
        if (is_dir($this->directory)) {
            return true;
        }

        return @mkdir($this->directory, 0777, true) || is_dir($this->directory);
    }
}

