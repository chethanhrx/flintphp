<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Http;

use JsonException;

/**
 * Immutable HTTP response.
 *
 * Represents an outgoing HTTP response with status code, headers,
 * and body. All mutation methods (withStatus, withHeader, withBody)
 * return new instances, leaving the original unchanged.
 *
 * This immutability is critical for middleware pipelines where
 * multiple layers may modify the response — mutable responses
 * lead to hard-to-trace bugs from shared state mutation.
 *
 * Design:
 * - Immutable: withX() returns new instances
 * - Status codes validated: must be 100–599
 * - JSON factory: Response::json() for API responses
 * - Testable: send() can be avoided in tests
 */
final class Response
{
    private readonly HeaderBag $headerBag;

    /**
     * @param string $body    The response body.
     * @param int    $status  The HTTP status code (100–599).
     * @param HeaderBag|array<string, string|string[]> $headers Response headers.
     *
     * @throws HttpException If the status code is outside the valid range.
     */
    public function __construct(
        private readonly string $body = '',
        private readonly int $status = 200,
        HeaderBag|array $headers = [],
    ) {
        self::validateStatusCode($status);

        $this->headerBag = $headers instanceof HeaderBag
            ? $headers
            : new HeaderBag($headers);
    }

    /**
     * Create a JSON response.
     *
     * Encodes the given data as JSON, sets the Content-Type header,
     * and returns a new Response instance.
     *
     * @param mixed $data   The data to JSON-encode.
     * @param int   $status The HTTP status code.
     * @param int   $flags  Additional JSON encoding flags.
     * @param HeaderBag|array<string, string|string[]> $headers Extra headers.
     *
     * @throws HttpException If JSON encoding fails.
     */
    public static function json(
        mixed $data,
        int $status = 200,
        int $flags = 0,
        HeaderBag|array $headers = [],
    ): self {
        $defaultFlags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE;

        try {
            $body = json_encode($data, $defaultFlags | $flags);
        } catch (JsonException $e) {
            throw new HttpException(
                'Failed to encode response data as JSON: ' . $e->getMessage(),
                previous: $e,
            );
        }

        $headerBag = $headers instanceof HeaderBag ? $headers : new HeaderBag($headers);
        $headerBag = $headerBag->withHeader('Content-Type', 'application/json');

        return new self(
            body: $body,
            status: $status,
            headers: $headerBag,
        );
    }

    /**
     * Get the HTTP status code.
     */
    public function status(): int
    {
        return $this->status;
    }

    /**
     * Get the response body.
     */
    public function body(): string
    {
        return $this->body;
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
     * Shortcut for $response->headers()->get($name).
     */
    public function header(string $name): ?string
    {
        return $this->headerBag->get($name);
    }

    /**
     * Return a new Response with a different status code.
     */
    public function withStatus(int $status): self
    {
        return new self(
            body: $this->body,
            status: $status,
            headers: $this->headerBag,
        );
    }

    /**
     * Return a new Response with the given header set.
     *
     * Replaces any existing header with the same name.
     */
    public function withHeader(string $name, string $value): self
    {
        return new self(
            body: $this->body,
            status: $this->status,
            headers: $this->headerBag->withHeader($name, $value),
        );
    }

    /**
     * Return a new Response with the given body.
     */
    public function withBody(string $body): self
    {
        return new self(
            body: $body,
            status: $this->status,
            headers: $this->headerBag,
        );
    }

    /**
     * Send the response to the client.
     *
     * Emits the status code, headers, and body to PHP's SAPI.
     * This method should only be called once per request lifecycle.
     *
     * In tests, work with status()/headers()/body() directly
     * instead of calling send().
     */
    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);

            foreach ($this->headerBag->all() as $name => $values) {
                foreach ($values as $i => $value) {
                    // First value replaces, subsequent values append
                    header(
                        sprintf('%s: %s', $name, $value),
                        replace: $i === 0,
                    );
                }
            }
        }

        echo $this->body;
    }

    /**
     * Validate that a status code is within the HTTP range.
     *
     * @throws HttpException If the status code is outside 100–599.
     */
    private static function validateStatusCode(int $status): void
    {
        if ($status < 100 || $status > 599) {
            throw new HttpException(
                sprintf('Invalid HTTP status code: %d. Must be between 100 and 599.', $status),
            );
        }
    }
}
