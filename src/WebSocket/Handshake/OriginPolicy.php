<?php

declare(strict_types=1);

namespace FlintPHP\Framework\WebSocket\Handshake;

use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\WebSocket\Exception\HandshakeException;
use InvalidArgumentException;

/**
 * Enforces an explicit allowlist for browser WebSocket Origin headers.
 *
 * Comparison uses normalized scheme, host, and effective port. Only HTTP(S)
 * origins without paths, credentials, queries, or fragments are accepted.
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
        if ($origin === '' || trim($origin) !== $origin
            || preg_match('/[\x00-\x20\x7f]/', $origin) === 1) {
            return null;
        }

        // Origin serialization contains only scheme and authority. This also
        // avoids accepting empty ports such as "https://example.com:".
        if (preg_match('~\Ahttps?://[^/?#]+\z~i', $origin) !== 1) {
            return null;
        }

        $authority = substr($origin, strpos($origin, '://') + 3);
        if ($authority === '' || str_ends_with($authority, ':')) {
            return null;
        }

        $parts = parse_url($origin);
        if (!is_array($parts)) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        $host = $parts['host'] ?? '';

        if (!in_array($scheme, ['http', 'https'], true) || $host === ''
            || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['path']) || isset($parts['query']) || isset($parts['fragment'])) {
            return null;
        }

        $normalizedHost = self::normalizeHost($host);
        if ($normalizedHost === null) {
            return null;
        }

        $port = $parts['port'] ?? null;
        if ($port !== null && ($port < 1 || $port > 65535)) {
            return null;
        }

        if (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) {
            $port = null;
        }

        return $scheme . '://' . $normalizedHost . ($port === null ? '' : ':' . $port);
    }

    private static function normalizeHost(string $host): ?string
    {
        // IPv6 literals must be bracketed in an origin and pass actual IP validation.
        if (str_starts_with($host, '[') || str_ends_with($host, ']')) {
            if (preg_match('/\A\[([0-9a-f:.]+)\]\z/i', $host, $matches) !== 1
                || filter_var($matches[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
                return null;
            }

            $packed = inet_pton($matches[1]);
            if ($packed === false) {
                return null;
            }

            $canonical = inet_ntop($packed);
            if ($canonical === false) {
                return null;
            }

            return '[' . strtolower($canonical) . ']';
        }

        // Valid IPv4 literals are accepted; numeric dotted forms that are not
        // valid IPv4 must not fall through and be treated as DNS names.
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return $host;
        }
        if (preg_match('/\A[0-9.]+\z/', $host) === 1) {
            return null;
        }

        // Accept ASCII DNS names (including localhost and IDNA A-labels), but
        // reject empty labels, overlong labels, and labels with edge hyphens.
        if (strlen($host) > 253 || str_ends_with($host, '.')) {
            return null;
        }

        foreach (explode('.', $host) as $label) {
            if (strlen($label) > 63
                || preg_match('/\A[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\z/i', $label) !== 1) {
                return null;
            }
        }

        return strtolower($host);
    }
}
