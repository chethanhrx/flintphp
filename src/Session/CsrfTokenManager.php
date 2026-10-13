<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Session;

/** Creates a session-bound synchronizer token and verifies it in constant time. */
final class CsrfTokenManager
{
    private const SESSION_KEY = '_flint_csrf_token';

    public function token(Session $session): string
    {
        $token = $session->get(self::SESSION_KEY);

        if (!is_string($token) || preg_match('/\A[a-f0-9]{64}\z/', $token) !== 1) {
            $token = bin2hex(random_bytes(32));
            $session->set(self::SESSION_KEY, $token);
        }

        return $token;
    }

    public function isValid(Session $session, ?string $candidate): bool
    {
        if ($candidate === null || $candidate === '') {
            return false;
        }

        $known = $session->get(self::SESSION_KEY);

        return is_string($known)
            && preg_match('/\A[a-f0-9]{64}\z/', $known) === 1
            && hash_equals($known, $candidate);
    }
}
