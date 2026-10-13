<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Session\Store;

use FlintPHP\Framework\Session\SessionStoreInterface;

/**
 * Process-local store intended for tests and local development only.
 *
 * It is not durable, is not shared between workers, and loses all sessions
 * when the PHP process exits. Use a durable shared implementation in production.
 */
final class ArraySessionStore implements SessionStoreInterface
{
    /**
     * @var array<string, array{data: array<string, mixed>, expiresAt: int}>
     */
    private array $sessions = [];

    public function read(string $id): ?array
    {
        $record = $this->sessions[$id] ?? null;

        if ($record === null) {
            return null;
        }

        if ($record['expiresAt'] <= time()) {
            unset($this->sessions[$id]);

            return null;
        }

        return $record['data'];
    }

    public function write(string $id, array $data, int $expiresAt): void
    {
        if ($expiresAt <= time()) {
            $this->delete($id);

            return;
        }

        $this->sessions[$id] = [
            'data' => $data,
            'expiresAt' => $expiresAt,
        ];
    }

    public function delete(string $id): void
    {
        unset($this->sessions[$id]);
    }
}
