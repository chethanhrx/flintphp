<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Authentication;

interface IdentityInterface
{
    /**
     * Get the unique identifier for the authenticated user/entity.
     */
    public function getIdentifier(): string|int;
}
