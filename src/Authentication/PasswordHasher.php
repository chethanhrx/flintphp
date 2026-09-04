<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Authentication;

use RuntimeException;

final class PasswordHasher
{
    /**
     * @param string $password
     * @return string
     * @throws RuntimeException
     */
    public function hash(string $password): string
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        if ($hash === false || $hash === '') {
            throw new RuntimeException('Failed to hash password.');
        }

        return $hash;
    }

    /**
     * @param string $password
     * @param string $hash
     * @return bool
     */
    public function verify(string $password, string $hash): bool
    {
        if ($hash === '') {
            return false;
        }

        return password_verify($password, $hash);
    }

    /**
     * @param string $hash
     * @return bool
     */
    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_DEFAULT);
    }
}
