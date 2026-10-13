<?php

declare(strict_types=1);

namespace FlintPHP\Framework\WebSocket\Handshake;

use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\WebSocket\Exception\HandshakeException;
use InvalidArgumentException;

/**
 * Enforces an explicit allowlist for browser WebSocket Origin headers.
 *
 * Origins are compared as scheme + host + effective port. Paths, credentials,
 * queries, fragments, whitespace, and non-HTTP(S) schemes are rejected.
 */
final class OriginPolicy
{
    /** @var list<string> */
    private readonly array $allowedOrigins;

    /**
     * @param list<string> $allowedOrigins Exact HTTP(S) origins, without paths.
     * @param bool $allowMissingOrigin Enable only for clients that legitimately omit Origin.
     */
    public function __construct(
        array $allowedOrigins,
        private readonly bool $allowMissingOrigin = false,
    ) {
        if ($allowedOrigins === []) {
            throw new InvalidArgumentException('At least one allowed WebSocket origin must be configured.');
        }

        $normalized = [];
        foreach ($allowedOrigins as $origin) {
            if (!is_string($origin) || ($canonical = self::normalizeOrigin($origin)) === null) {
                throw new InvalidArgumentException('Invalid allowed WebSocket origin.');
            }

            $normalized[] = $canonical;
        }

        $this->allowedOrigins = array_values(array_unique($normalized));
    }

    /**
     * @throws HandshakeException When the request origin is absent, malformed, or not allowed.
     */
    public function validate(Request $request): void
    {
        $origins = $request->headers()->getAll('Origin');

        if ($origins === [] && $this->allowMissingOrigin) {
            return;
        }

        if (count($origins) !== 1) {
            throw new HandshakeException('Exactly one allowed Origin header is required.');
        }

        $origin = self::normalizeOrigin($origins[0]);
        if ($origin === null || !in_array($origin, $this->allowedOrigins, true)) {
            throw new HandshakeException('WebSocket Origin is not allowed.');
        }
    }

    private static function normalizeOrigin(string $origin): ?string
    {
        if ($origin === '' || trim($origin) !== $origin || preg_match('/[\x00-\x20\x7f]/', $origin) === 1) {
            return null;
        }

        $parts = parse_url($origin);
        if (!is_array($parts)) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        $host = $parts['host'] ?? '';

        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        // Origin serialization has no path, user-info, query, or fragment.
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['path'])
            || isset($parts['query']) || isset($parts['fragment'])) {
            return null;
        }

        if (preg_match('/\A(?:[a-z0-9.-]+|\[[0-9a-f:.]+\])\z/i', $host) !== 1) {
            return null;
        }

        $port = $parts['port'] ?? null;
        if ($port !== null && ($port < 1 || $port > 65535)) {
            return null;
        }

        if (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) {
            $port = null;
        }

        return $scheme . '://' . strtolower($host) . ($port === null ? '' : ':' . $port);
    }
}
