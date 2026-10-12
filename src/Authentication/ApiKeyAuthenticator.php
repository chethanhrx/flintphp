<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Authentication;

use FlintPHP\Framework\Authentication\Exception\InvalidCredentialsException;
use FlintPHP\Framework\Authentication\Exception\MissingCredentialsException;
use FlintPHP\Framework\Http\Request;

/**
 * Authenticate via X-Api-Key header.
 *
 * The raw key is SHA-256 hashed before UserProvider lookup (same as Bearer).
 */
final class ApiKeyAuthenticator implements AuthenticatorInterface
{
    public function __construct(
        private readonly UserProviderInterface $provider,
        private readonly string $headerName = 'X-Api-Key',
    ) {
    }

    public function authenticate(Request $request): IdentityInterface
    {
        $key = $request->header($this->headerName);

        if ($key === null || trim($key) === '') {
            throw new MissingCredentialsException(sprintf('%s header is missing.', $this->headerName));
        }

        $key = trim($key);
        $hashed = hash('sha256', $key);

        $identity = $this->provider->retrieveByToken($hashed);

        if ($identity === null) {
            throw new InvalidCredentialsException('Invalid API key.');
        }

        return $identity;
    }
}
