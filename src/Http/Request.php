<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Http;

use FlintPHP\Framework\Http\Exception\HttpException;
use FlintPHP\Framework\Security\Http\TrustedProxyConfiguration;
use JsonException;

/**
 * Immutable HTTP request.
 *
 * Path rules:
 * - Strip query string (?) and fragment (#) without parse_url
 * - Require a leading /
 * - Collapse consecutive slashes (//a → /a) so routing cannot disagree with common proxies
 * - Segments are NOT percent-decoded (handlers receive raw segment text)
 */
final class Request
{
    private readonly HeaderBag $headerBag;

    /**
     * @param HeaderBag|array<string, string|string[]> $headers
     * @param array<string, mixed> $server
     * @param array<string, string> $cookies
     * @param array<string, mixed> $query
     * @param array<string, mixed> $attributes
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

    public static function fromGlobals(?TrustedProxyConfiguration $trustedProxies = null): self
    {
        $server = $_SERVER;

        return new self(
            method: strtoupper($server['REQUEST_METHOD'] ?? 'GET'),
            uri: $server['REQUEST_URI'] ?? '/',
            headers: self::extractHeaders($server),
            body: self::readBody(),
            server: $server,
            cookies: $_COOKIE,
            query: $_GET,
            attributes: [],
            clientIp: self::resolveClientIp($server, $trustedProxies),
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function httpMethod(): ?Method
    {
        return Method::tryFrom($this->method);
    }

    public function isMethod(string $method): bool
    {
        return strtoupper($method) === $this->method;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function path(): string
    {
        $path = $this->uri;

        $q = strpos($path, '?');
        if ($q !== false) {
            $path = substr($path, 0, $q);
        }

        $h = strpos($path, '#');
        if ($h !== false) {
            $path = substr($path, 0, $h);
        }

        if ($path === '' || $path[0] !== '/') {
            return '/';
        }

        // Collapse // so //evil/api cannot bypass simple string checks inconsistently
        $collapsed = preg_replace('#/{2,}#', '/', $path);

        return ($collapsed !== null && $collapsed !== '') ? $collapsed : '/';
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }

        return array_key_exists($key, $this->query) ? $this->query[$key] : $default;
    }

    public function headers(): HeaderBag
    {
        return $this->headerBag;
    }

    public function header(string $name): ?string
    {
        return $this->headerBag->get($name);
    }

    public function body(): string
    {
        return $this->body;
    }

    /**
     * Decode JSON body. Empty body → []. Invalid JSON / non-array → HttpException 400.
     *
     * @return array<string, mixed>|list<mixed>
     */
    public function json(): array
    {
        if ($this->body === '') {
            return [];
        }

        try {
            $decoded = json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new HttpException(400, 'Invalid JSON body', $e);
        }

        if (!is_array($decoded)) {
            throw new HttpException(400, 'JSON body must be an object or array');
        }

        return $decoded;
    }

    public function server(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->server;
        }

        return array_key_exists($key, $this->server) ? $this->server[$key] : $default;
    }

    /** @return array<string, string> */
    public function cookies(): array
    {
        return $this->cookies;
    }

    public function cookie(string $name): ?string
    {
        return $this->cookies[$name] ?? null;
    }

    /** @param array<string, mixed> $server
     *  @return array<string, string>
     */
    private static function extractHeaders(array $server): array
    {
        $headers = [];

        foreach ($server as $key => $value) {
            if (!is_string($value)) {
                continue;
            }

            if (str_starts_with($key, 'HTTP_')) {
                $headers[str_replace('_', '-', substr($key, 5))] = $value;
            } elseif ($key === 'CONTENT_TYPE') {
                $headers['Content-Type'] = $value;
            } elseif ($key === 'CONTENT_LENGTH') {
                $headers['Content-Length'] = $value;
            }
        }

        return $headers;
    }

    /** @deprecated Use attribute() */
    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attribute($key, $default);
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->attributes) ? $this->attributes[$key] : $default;
    }

    public function hasAttribute(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

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

    public function withoutAttribute(string $key): static
    {
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

    public function attributes(): array
    {
        return $this->attributes;
    }

    public function withHeader(string $name, string $value): static
    {
        return new static(
            method: $this->method,
            uri: $this->uri,
            headers: $this->headerBag->withHeader($name, $value),
            body: $this->body,
            server: $this->server,
            cookies: $this->cookies,
            query: $this->query,
            attributes: $this->attributes,
            clientIp: $this->clientIp,
        );
    }

    public const MAX_BODY_BYTES = 10_485_760; // 10 MiB default body limit

    private static function readBody(): string
    {
        $body = @file_get_contents('php://input', false, null, 0, self::MAX_BODY_BYTES + 1);

        if ($body === false) {
            return '';
        }

        if (strlen($body) > self::MAX_BODY_BYTES) {
            throw new HttpException(413, 'Payload Too Large: request body exceeds 10 MiB limit');
        }

        return $body;
    }

    public function clientIp(): ?string
    {
        return $this->clientIp;
    }

    /** @param array<string, mixed> $server */
    private static function resolveClientIp(array $server, ?TrustedProxyConfiguration $trustedProxies): ?string
    {
        $remoteAddr = $server['REMOTE_ADDR'] ?? null;

        if ($remoteAddr === null || $trustedProxies === null || !$trustedProxies->isTrusted($remoteAddr)) {
            return $remoteAddr;
        }

        if (isset($server['HTTP_X_FORWARDED_FOR'])) {
            $ips = array_reverse(array_map('trim', explode(',', $server['HTTP_X_FORWARDED_FOR'])));
            foreach ($ips as $ip) {
                if (!$trustedProxies->isTrusted($ip)) {
                    return $ip;
                }
            }

            return end($ips) ?: $remoteAddr;
        }

        return $remoteAddr;
    }
}
