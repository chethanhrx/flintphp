<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Authentication;

interface UserProviderInterface
{
    /**
     * Retrieve a user by their unique token.
     *
     * @param string $token
     * @return IdentityInterface|null
     */
    public function retrieveByToken(string $token): ?IdentityInterface;
}
