<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Config\Contract;

interface ConfigRepositoryInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function has(string $key): bool;

    public function all(): array;
}
