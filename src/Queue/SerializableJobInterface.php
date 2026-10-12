<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Queue;

/**
 * Jobs that can be persisted. Required by DatabaseQueue.
 */
interface SerializableJobInterface extends JobInterface
{
    /** @return array<string, mixed> */
    public function toArray(): array;

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): static;
}
