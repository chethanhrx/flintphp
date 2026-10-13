<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Session;

/**
 * Stores session payloads on the server; clients receive only an opaque ID.
 *
 * Implementations must treat $expiresAt as an absolute Unix timestamp and
 * return null for missing or expired sessions. Production applications should
 * use a shared, durable backend when requests can reach multiple workers.
 */
interface SessionStoreInterface
{
    /**
     * @return array<string, mixed>|null Null when absent or expired.
     */
    public function read(string $id): ?array;

    /**
     * @param array<string, mixed> $data
     */
    public function write(string $id, array $data, int $expiresAt): void;

    public function delete(string $id): void;
}
