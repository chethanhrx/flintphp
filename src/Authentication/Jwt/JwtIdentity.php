<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Authentication\Jwt;

use FlintPHP\Framework\Authentication\IdentityInterface;

final class JwtIdentity implements IdentityInterface
{
    /**
     * @param array<string, mixed> $claims
     */
    public function __construct(
        private readonly string|int $identifier,
        private readonly array $claims = [],
    ) {
    }

    public function getIdentifier(): string|int
    {
        return $this->identifier;
    }

    /** @return array<string, mixed> */
    public function claims(): array
    {
        return $this->claims;
    }
}
