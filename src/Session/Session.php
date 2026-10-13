<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Session;

/**
 * Request-scoped session data. This object does not persist itself.
 *
 * Keep values serializable by the configured SessionStoreInterface. Avoid
 * storing service objects, resources, or secrets that do not belong in a
 * session payload.
 */
final class Session
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private readonly string $id,
        private array $data = [],
    ) {
    }

    /** Opaque server-side lookup key; do not expose it to templates or logs. */
    public function id(): string
    {
        return $this->id;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->data) ? $this->data[$key] : $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->data;
    }

    public function clear(): void
    {
        $this->data = [];
    }
}
