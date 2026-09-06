<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Http;

use FlintPHP\Framework\Security\Http\TrustedProxyConfiguration;

/**
 * Immutable HTTP request representation.
 *
 * Wraps all incoming HTTP request data in a clean, typed, read-only
 * object. All data is treated as untrusted — no automatic type casting,
 * no header trust, no input interpretation.
 *
 * Construct from PHP superglobals via Request::fromGlobals(), or
 * build manually for testing.
 *
 * Design:
 * - Immutable: request data should not change after construction
 * - No global access: superglobals are only read in fromGlobals()
 * - No magic: explicit methods for each data source
 * - Composition: uses HeaderBag for header management
 */
final class Request
{
    private readonly HeaderBag $headerBag;

    /**
     * @param string $method   The HTTP method (e.g., 'GET', 'POST').
     * @param string $uri      The full request URI including query string.
     * @param HeaderBag|array<string, string|string[]> $headers Request headers.
     * @param string $body     The raw request body.
     * @param array<string, mixed> $server  Server/environment parameters.
     * @param array<string, string> $cookies Request cookies.
     * @param array<string, mixed> $query   Parsed query parameters.
     * @param array<string, mixed> $attributes Custom request attributes.
     * @param string|null $clientIp The resolved client IP address.
     */
    public function __construct(
        private readonly string $method,
        private readonly string $uri,
        HeaderBag|array $headers = [],
        private readonly string $body = '',
        private readonly array $server = [],
        private readonly array $cookies = [],
        private readonly array $query = [],
        private readonly array $attributes = [],
        private readonly ?string $clientIp = null,
    ) {
        $this->headerBag = $headers instanceof HeaderBag
            ? $headers
            : new HeaderBag($headers);
    }

    /**
     * Create a Request from PHP superglobals.
     *
     * This is the ONLY place in the framework that reads PHP
     * superglobals directly. All other code works with the
     * Request object.
     */
    public static function fromGlobals(?TrustedProxyConfiguration $trustedProxies = null): self
    {
        $server = $_SERVER;
        $method = strtoupper($server['REQUEST_METHOD'] ?? 'GET');
        $uri = $server['REQUEST_URI'] ?? '/';

        $clientIp = self::resolveClientIp($server, $trustedProxies);

        return new self(
            method: $method,
            uri: $uri,
            headers: self::extractHeaders($server),
            body: self::readBody(),
            server: $server,
            cookies: $_COOKIE,
            query: $_GET,
            attributes: [],
            clientIp: $clientIp,
        );
    }

    /**
     * Get the HTTP method (uppercase string).
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * Get the HTTP method as a typed enum, if it is a standard method.
     *
     * Returns null for non-standard methods (e.g., PURGE, PROPFIND).
     */
    public function httpMethod(): ?Method
    {
        return Method::tryFrom($this->method);
    }

    /**
     * Check if the request uses the given HTTP method (case-insensitive).
     */
    public function isMethod(string $method): bool
    {
        return strtoupper($method) === $this->method;
    }

    /**
     * Get the full request URI including query string.
     *
     * Example: '/users/42?active=true'
     */
    public function uri(): string
    {
        return $this->uri;
    }

    /**
     * Get the request path without the query string.
     *
     * Example: '/users/42' from '/users/42?active=true'
     */
    public function path(): string
    {
        $path = parse_url($this->uri, PHP_URL_PATH);

        return $path !== null && $path !== false ? $path : '/';
    }

    /**
     * Get query parameters.
     *
     * Without arguments: returns all query parameters as an array.
     * With a key: returns the value for that key, or the default.
     *
     * @param string|null $key     The query parameter key, or null for all.
     * @param mixed       $default The default value if the key is missing.
     * @return mixed
     */
    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }

        return $this->query[$key] ?? $default;
    }

    /**
     * Get the header bag.
     */
    public function headers(): HeaderBag
    {
        return $this->headerBag;
    }

    /**
     * Get a single header value (case-insensitive).
     *
     * Shortcut for $request->headers()->get($name).
     */
    public function header(string $name): ?string
    {
        return $this->headerBag->get($name);
    }

    /**
     * Get the raw request body.
     */
    public function body(): string
    {
        return $this->body;
    }

    /**
     * Get server parameters.
     *
     * Without arguments: returns all server parameters.
     * With a key: returns the value for that key, or the default.
     *
     * @param string|null $key     The server parameter key, or null for all.
     * @param mixed       $default The default value if the key is missing.
     * @return mixed
     */
    public function server(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->server;
        }

        return $this->server[$key] ?? $default;
    }

    /**
     * Get all cookies.
     *
     * @return array<string, string>
     */
    public function cookies(): array
    {
        return $this->cookies;
    }

    /**
     * Get a single cookie value.
     */
    public function cookie(string $name): ?string
    {
        return $this->cookies[$name] ?? null;
    }

    /**
     * Extract HTTP headers from $_SERVER parameters.
     *
     * HTTP headers in $_SERVER are prefixed with HTTP_ and use
     * underscores instead of hyphens. Content-Type and Content-Length
     * are special cases without the HTTP_ prefix.
     *
     * @param array<string, mixed> $server
     * @return array<string, string>
     */
    private static function extractHeaders(array $server): array
    {
        $headers = [];

        foreach ($server as $key => $value) {
            if (!is_string($value)) {
                continue;
            }

            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[$name] = $value;
            } elseif ($key === 'CONTENT_TYPE') {
                $headers['Content-Type'] = $value;
            } elseif ($key === 'CONTENT_LENGTH') {
                $headers['Content-Length'] = $value;
            }
        }

        return $headers;
    }

    /**
     * Get a specific request attribute.
     *
     * @deprecated Use attribute() instead.
     */
    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attribute($key, $default);
    }

    /**
     * Get an attribute by key, returning the default if missing.
     */
    public function attribute(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }

        return $default;
    }

    /**
     * Check if the request has an attribute by key.
     */
    public function hasAttribute(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    /**
     * Return a new Request with the specified attribute.
     */
    public function withAttribute(string $key, mixed $value): static
    {
        $attributes = $this->attributes;
        $attributes[$key] = $value;

        return new static(
            method: $this->method,
            uri: $this->uri,
            headers: clone $this->headerBag,
            body: $this->body,
            server: $this->server,
            cookies: $this->cookies,
            query: $this->query,
            attributes: $attributes,
            clientIp: $this->clientIp,
        );
    }

    /**
     * Return a new Request without the specified attribute.
     */
    public function withoutAttribute(string $key): static
    {
        if (!array_key_exists($key, $this->attributes)) {
            // Already absent; but to satisfy $new !== $this reliably for immutable semantics,
            // we should still return a clone conceptually. However, if performance matters,
            // returning $this is standard for immutable PSR-7.
            // The prompt says "is acceptable and preferred for consistent immutable semantics"
            // regarding $newRequest !== $request.
        }

        $attributes = $this->attributes;
        unset($attributes[$key]);

        return new static(
            method: $this->method,
            uri: $this->uri,
            headers: clone $this->headerBag,
            body: $this->body,
            server: $this->server,
            cookies: $this->cookies,
            query: $this->query,
            attributes: $attributes,
            clientIp: $this->clientIp,
        );
    }

    /**
     * Get all attributes.
     */
    public function attributes(): array
    {
        return $this->attributes;
    }

    /**
     * Return a new Request with the given header.
     */
    public function withHeader(string $name, string $value): static
    {
        $headers = $this->headerBag->withHeader($name, $value);

        return new static(
            method: $this->method,
            uri: $this->uri,
            headers: $headers,
            body: $this->body,
            server: $this->server,
            cookies: $this->cookies,
            query: $this->query,
            attributes: $this->attributes,
            clientIp: $this->clientIp,
        );
    }

    /**
     * Read the raw request body from php://input.
     *
     * Returns an empty string if the body cannot be read.
     */
    private static function readBody(): string
    {
        $body = file_get_contents('php://input');

        return $body !== false ? $body : '';
    }

    /**
     * Get the resolved client IP address.
     */
    public function clientIp(): ?string
    {
        return $this->clientIp;
    }

    /**
     * Resolve the client IP address from server variables and trusted proxies.
     *
     * @param array<string, mixed> $server
     * @param TrustedProxyConfiguration|null $trustedProxies
     * @return string|null
     */
    private static function resolveClientIp(array $server, ?TrustedProxyConfiguration $trustedProxies): ?string
    {
        $remoteAddr = $server['REMOTE_ADDR'] ?? null;

        if ($remoteAddr === null || $trustedProxies === null || !$trustedProxies->isTrusted($remoteAddr)) {
            return $remoteAddr;
        }

        if (isset($server['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $server['HTTP_X_FORWARDED_FOR']);
            $ips = array_map('trim', $ips);

            // Go from right to left, if an IP is trusted, keep going left.
            // If we find an untrusted one, that's the client IP.
            $ips = array_reverse($ips);
            foreach ($ips as $ip) {
                if (!$trustedProxies->isTrusted($ip)) {
                    return $ip;
                }
            }

            // If all IPs in the chain are trusted proxies, the client IP is the leftmost one
            return end($ips);
        }

        return $remoteAddr;
    }
}
