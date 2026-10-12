<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Authentication;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class PasswordHasher
{
    /**
     * @param string|int|null $algo Password hashing algorithm (defaults to PASSWORD_DEFAULT)
     * @param array<string, mixed> $options Algorithm options (e.g. ['cost' => 12])
     */
    public function __construct(
        private readonly string|int|null $algo = PASSWORD_DEFAULT,
        private readonly array $options = [],
    ) {
    }

    /**
     * Hash a plaintext password.
     *
     * @param string $password
     * @return string
     *
     * @throws InvalidArgumentException If the password is empty
     * @throws RuntimeException If hashing fails
     */
    public function hash(string $password): string
    {
        if ($password === '') {
            throw new InvalidArgumentException('Password must not be empty.');
        }

        try {
            $hash = password_hash($password, $this->algo, $this->options);
        } catch (Throwable $e) {
            throw new RuntimeException('Failed to hash password: ' . $e->getMessage(), 0, $e);
        }

        return $hash;
    }

    /**
     * Verify a plaintext password against a hash.
     *
     * @param string $password
     * @param string $hash
     * @return bool
     */
    public function verify(string $password, string $hash): bool
    {
        if ($password === '' || $hash === '') {
            return false;
        }

        return password_verify($password, $hash);
    }

    /**
     * Determine if the given hash needs to be rehashed using the configured algorithm and options.
     *
     * @param string $hash
     * @return bool
     */
    public function needsRehash(string $hash): bool
    {
        if ($hash === '') {
            return true;
        }

        return password_needs_rehash($hash, $this->algo, $this->options);
    }
}
