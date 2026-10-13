<?php

declare(strict_types=1);

namespace FlintPHP\\Framework\\Session\\Store;

use FlintPHP\\Framework\\Session\\SessionStoreInterface;
use JsonException;
use RuntimeException;

/**
 * Durable file-backed session store for single-host deployments.
 *
 * The storage directory must be outside the public document root. For
 * multi-host deployments, use a shared database or cache implementation.
 */
final class FileSessionStore implements SessionStoreInterface
{
    public function __construct(private readonly string $directory)
    {
        $created = false;

        if (!is_dir($this->directory)) {
            if (!@mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
                throw new RuntimeException('Unable to create the session storage directory.');
            }

            $created = true;
        }

        if (!is_dir($this->directory) || is_link($this->directory)) {
            throw new RuntimeException('Session storage path must be a real directory, not a symbolic link.');
        }

        if ($created && !@chmod($this->directory, 0700)) {
            throw new RuntimeException('Unable to secure session storage directory permissions.');
        }

        $permissions = fileperms($this->directory);

        if ($permissions === false || ($permissions & 0077) !== 0) {
            throw new RuntimeException('Session storage directory must not grant group or other permissions.');
        }

        if (!is_writable($this->directory)) {
            throw new RuntimeException('Session storage directory is not writable.');
        }
    }

    public function read(string $id): ?array
    {
        $this->assertValidId($id);
        $lock = $this->lock($id, LOCK_SH);

        try {
            $path = $this->sessionPath($id);

            if (is_link($path) || !is_file($path)) {
                return null;
            }

            $contents = @file_get_contents($path);

            if ($contents === false) {
                throw new RuntimeException('Unable to read stored session.');
            }

            try {
                $record = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                // Treat malformed storage as an invalid session, never as trusted data.
                return null;
            }

            if (
                !is_array($record)
                || !isset($record['expiresAt'], $record['data'])
                || !is_int($record['expiresAt'])
                || !is_array($record['data'])
            ) {
                return null;
            }

            if ($record['expiresAt'] <= time()) {
                return null;
            }

            /** @var array<string, mixed> $data */
            $data = $record['data'];

            return $data;
        } finally {
            $this->unlock($lock);
        }
    }

    public function write(string $id, array $data, int $expiresAt): void
    {
        $this->assertValidId($id);

        if ($expiresAt <= time()) {
            $this->delete($id);

            return;
        }

        try {
            $contents = json_encode(
                ['expiresAt' => $expiresAt, 'data' => $data],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            );
        } catch (JsonException $exception) {
            throw new RuntimeException('Session data must be JSON-serializable.', 0, $exception);
        }

        $lock = $this->lock($id, LOCK_EX);

        try {
            $path = $this->sessionPath($id);

            if (is_link($path)) {
                throw new RuntimeException('Refusing to write through a symbolic link in session storage.');
            }

            $temporaryPath = $this->directory . DIRECTORY_SEPARATOR . '.' . $id . '.' . bin2hex(random_bytes(8)) . '.tmp';
            $written = false;

            try {
                $bytes = @file_put_contents($temporaryPath, $contents, LOCK_EX);

                if ($bytes === false || $bytes !== strlen($contents)) {
                    throw new RuntimeException('Unable to write session data.');
                }

                if (!@chmod($temporaryPath, 0600)) {
                    throw new RuntimeException('Unable to secure session file permissions.');
                }

                if (!@rename($temporaryPath, $path)) {
                    throw new RuntimeException('Unable to atomically replace stored session.');
                }

                $written = true;
            } finally {
                if (!$written && is_file($temporaryPath)) {
                    @unlink($temporaryPath);
                }
            }
        } finally {
            $this->unlock($lock);
        }
    }

    public function delete(string $id): void
    {
        $this->assertValidId($id);
        $lock = $this->lock($id, LOCK_EX);

        try {
            $path = $this->sessionPath($id);

            if (is_link($path)) {
                throw new RuntimeException('Refusing to delete a symbolic link in session storage.');
            }

            if (is_file($path) && !@unlink($path)) {
                throw new RuntimeException('Unable to delete stored session.');
            }
        } finally {
            $this->unlock($lock);
        }
    }

    private function assertValidId(string $id): void
    {
        if (preg_match('/\\A[a-f0-9]{64}\\z/', $id) !== 1) {
            throw new RuntimeException('Invalid session ID.');
        }
    }

    private function sessionPath(string $id): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . $id . '.json';
    }

    /**
     * @return resource
     */
    private function lock(string $id, int $operation)
    {
        $path = $this->directory . DIRECTORY_SEPARATOR . '.' . $id . '.lock';

        if (is_link($path)) {
            throw new RuntimeException('Refusing to use a symbolic link as a session lock.');
        }

        $handle = @fopen($path, 'c');

        if ($handle === false) {
            throw new RuntimeException('Unable to open session lock.');
        }

        @chmod($path, 0600);

        if (!flock($handle, $operation)) {
            fclose($handle);
            throw new RuntimeException('Unable to acquire session lock.');
        }

        return $handle;
    }

    /**
     * @param resource $handle
     */
    private function unlock($handle): void
    {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
