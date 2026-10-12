<?php

declare(strict_types=1);

namespace FlintPHP\\Framework\\Cache;

use FlintPHP\\Framework\\Cache\\Exception\\CacheStorageException;
use FlintPHP\\Framework\\Cache\\Exception\\InvalidArgumentException;
use FlintPHP\\Framework\\Cache\\Exception\\InvalidCacheValueException;
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
        if (!is_dir($this->directory)) {
            return $default;
        }

        return $this->withKeyLock($key, function (string $path) use ($default): mixed {
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
        });
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $this->validateKey($key);

        if ($ttl !== null && $ttl <= 0) {
            return $this->delete($key);
        }

        if (!$this->ensureDirectory()) {
            return false;
        }

        try {
            $payload = json_encode([
                'expires_at' => $ttl === null ? null : time() + $ttl,
                'value' => $value,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $e) {
            throw new InvalidCacheValueException('Cannot serialize value to JSON for cache: ' . $e->getMessage(), 0, $e);
        }

        return $this->withKeyLock(
            $key,
            fn (string $path): bool => $this->writePayloadAtomically($path, $payload)
        );
    }

    public function delete(string $key): bool
    {
        $this->validateKey($key);
        if (!is_dir($this->directory)) {
            return true;
        }

        return $this->withKeyLock($key, static function (string $path): bool {
            return !is_file($path) || @unlink($path);
        });
    }

    public function clear(): bool
    {
        if (!is_dir($this->directory)) {
            return true;
        }

        $success = true;
        $iterator = new DirectoryIterator($this->directory);

        foreach ($iterator as $file) {
            $filename = $file->getFilename();
            if (!$file->isFile() || preg_match('/\\A([a-f0-9]{64})\\.json\\z/', $filename, $matches) !== 1) {
                continue;
            }

            $hash = $matches[1];
            try {
                $deleted = $this->withHashLock($hash, static function (string $path): bool {
                    return !is_file($path) || @unlink($path);
                });
                $success = $deleted && $success;
            } catch (CacheStorageException) {
                $success = false;
            }
        }

        return $success;
    }

    public function has(string $key): bool
    {
        $this->validateKey($key);
        if (!is_dir($this->directory)) {
            return false;
        }

        return $this->withKeyLock($key, static function (string $path): bool {
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
        });
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

        return $this->withKeyLock($key, function (string $path) use ($step, $ttl): int {
            $data = null;
            if (is_file($path)) {
                $content = @file_get_contents($path);
                if ($content !== false && $content !== '') {
                    try {
                        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
                    } catch (JsonException) {
                        $data = null;
                    }
                }
            }

            $valid = is_array($data)
                && array_key_exists('expires_at', $data)
                && array_key_exists('value', $data)
                && ($data['expires_at'] === null || (is_int($data['expires_at']) && $data['expires_at'] >= time()));

            if (!$valid) {
                $expiresAt = $ttl === null ? null : time() + $ttl;
                $newValue = $step;
            } else {
                $expiresAt = $data['expires_at'];
                $current = $data['value'];
                $currentInt = is_int($current) ? $current : (is_numeric($current) ? (int) $current : 0);
                if ($currentInt > PHP_INT_MAX - $step) {
                    throw new CacheStorageException('Cache counter would overflow an integer.');
                }
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

            if (!$this->writePayloadAtomically($path, $payload)) {
                throw new CacheStorageException(sprintf('Failed to write cache counter "%s".', $path));
            }

            return $newValue;
        });
    }

    private function validateKey(string $key): void
    {
        if (!preg_match('/\\A[a-zA-Z0-9_.]{1,64}\\z/', $key)) {
            throw new InvalidArgumentException(sprintf('Invalid cache key: "%s"', $key));
        }
    }

    private function path(string $key): string
    {
        return $this->pathForHash(hash('sha256', $key));
    }

    private function pathForHash(string $hash): string
    {
        return rtrim($this->directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $hash . '.json';
    }

    private function lockPath(string $hash): string
    {
        return rtrim($this->directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $hash . '.lock';
    }

    /**
     * A stable sidecar lock is used instead of locking the data file itself.
     * The data file is atomically replaced by set()/increment(); locking that
     * inode would allow waiters to hold a lock on an obsolete file.
     */
    private function withKeyLock(string $key, callable $callback): mixed
    {
        return $this->withHashLock(hash('sha256', $key), fn (string $path): mixed => $callback($path));
    }

    private function withHashLock(string $hash, callable $callback): mixed
    {
        if (!$this->ensureDirectory()) {
            throw new CacheStorageException(sprintf('Failed to create cache directory "%s".', $this->directory));
        }

        $lockPath = $this->lockPath($hash);
        $lock = @fopen($lockPath, 'c');
        if ($lock === false) {
            throw new CacheStorageException(sprintf('Failed to open cache lock "%s".', $lockPath));
        }

        @chmod($lockPath, 0600);

        if (!flock($lock, LOCK_EX)) {
            fclose($lock);
            throw new CacheStorageException(sprintf('Failed to acquire cache lock "%s".', $lockPath));
        }

        try {
            return $callback($this->pathForHash($hash));
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function writePayloadAtomically(string $path, string $payload): bool
    {
        $directory = rtrim($this->directory, DIRECTORY_SEPARATOR);
        $tempPath = @tempnam($directory, '.flint-cache-');
        if ($tempPath === false) {
            return false;
        }

        @chmod($tempPath, 0600);
        $written = @file_put_contents($tempPath, $payload, LOCK_EX);
        if ($written !== strlen($payload)) {
            @unlink($tempPath);
            return false;
        }

        if (!@rename($tempPath, $path)) {
            @unlink($tempPath);
            return false;
        }

        @chmod($path, 0600);
        return true;
    }

    private function ensureDirectory(): bool
    {
        if (is_dir($this->directory)) {
            return true;
        }

        return @mkdir($this->directory, 0700, true) || is_dir($this->directory);
    }
}
