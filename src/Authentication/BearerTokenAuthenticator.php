<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Authentication;

use FlintPHP\Framework\Authentication\Exception\InvalidCredentialsException;
use FlintPHP\Framework\Authentication\Exception\MissingCredentialsException;
use FlintPHP\Framework\Http\Request;

final class BearerTokenAuthenticator implements AuthenticatorInterface
{
    public function __construct(private readonly UserProviderInterface $provider)
    {
    }

    public function authenticate(Request $request): IdentityInterface
    {
        $authorization = $request->header('Authorization');

        if ($authorization === null || $authorization === '') {
            throw new MissingCredentialsException('Authorization header is missing.');
        }

        if (!str_starts_with(strtolower($authorization), 'bearer ')) {
            throw new MissingCredentialsException('Authorization header must use the Bearer scheme.');
        }

        $token = substr($authorization, 7);
        $token = trim($token);

        if ($token === '') {
            throw new InvalidCredentialsException('Bearer token is empty.');
        }

        // We hash the token so the UserProvider can do a secure lookup
        // against a hashed database column to prevent token theft from the DB.
        $hashedToken = hash('sha256', $token);

        $identity = $this->provider->retrieveByToken($hashedToken);

        if ($identity === null) {
            throw new InvalidCredentialsException('Invalid Bearer token.');
        }

        return $identity;
    }
}
