<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Session;

use InvalidArgumentException;

/** Explicit cookie and lifetime settings for SessionMiddleware. */
final class SessionCookieConfiguration
{
    public function __construct(
        public readonly string $name = '__Host-flint_session',
        public readonly int $lifetimeSeconds = 1800,
        public readonly string $path = '/',
        public readonly ?string $domain = null,
        public readonly bool $secure = true,
        public readonly bool $httpOnly = true,
        public readonly string $sameSite = 'Lax',
    ) {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $name)) {
            throw new InvalidArgumentException('Invalid session cookie name.');
        }

        if ($lifetimeSeconds < 1) {
            throw new InvalidArgumentException('Session lifetime must be positive.');
        }

        if ($path === '' || preg_match('/[;\r\n]/', $path) === 1) {
            throw new InvalidArgumentException('Invalid session cookie path.');
        }

        if ($domain !== null && (preg_match('/^[A-Za-z0-9.-]+$/', $domain) !== 1 || str_contains($domain, '..'))) {
            throw new InvalidArgumentException('Invalid session cookie domain.');
        }

        if (!in_array($sameSite, ['Strict', 'Lax', 'None'], true)) {
            throw new InvalidArgumentException('SameSite must be Strict, Lax, or None.');
        }

        if ($sameSite === 'None' && !$secure) {
            throw new InvalidArgumentException('SameSite=None requires Secure cookies.');
        }

        if (str_starts_with($name, '__Host-') && (!$secure || $path !== '/' || $domain !== null)) {
            throw new InvalidArgumentException('__Host- cookies require Secure, Path=/, and no Domain.');
        }
    }

    public function serialize(string $id): string
    {
        $cookie = $this->name . '=' . rawurlencode($id)
            . '; Path=' . $this->path
            . '; Max-Age=' . $this->lifetimeSeconds;

        if ($this->domain !== null) {
            $cookie .= '; Domain=' . $this->domain;
        }

        if ($this->secure) {
            $cookie .= '; Secure';
        }

        if ($this->httpOnly) {
            $cookie .= '; HttpOnly';
        }

        return $cookie . '; SameSite=' . $this->sameSite;
    }
}
