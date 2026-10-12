<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Authentication\Jwt;

use FlintPHP\Framework\Authentication\AuthenticatorInterface;
use FlintPHP\Framework\Authentication\Exception\InvalidCredentialsException;
use FlintPHP\Framework\Authentication\Exception\MissingCredentialsException;
use FlintPHP\Framework\Authentication\IdentityInterface;
use FlintPHP\Framework\Http\Request;

/**
 * Validates HS256 Bearer JWTs. Identity is built from the `sub` claim.
 */
final class JwtAuthenticator implements AuthenticatorInterface
{
    public function __construct(
        private readonly string $secret,
    ) {
        if ($this->secret === '') {
            throw new \InvalidArgumentException('JWT secret must not be empty');
        }
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

        $token = trim(substr($authorization, 7));
        if ($token === '') {
            throw new InvalidCredentialsException('Bearer token is empty.');
        }

        $claims = HmacJwt::decode($token, $this->secret);

        if (!isset($claims['sub']) || (!is_string($claims['sub']) && !is_int($claims['sub']))) {
            throw new InvalidCredentialsException('JWT missing sub claim');
        }

        return new JwtIdentity($claims['sub'], $claims);
    }
}
