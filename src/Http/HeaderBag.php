<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Http;

/**
 * Case-insensitive HTTP header collection.
 *
 * Stores headers with lowercased keys internally for case-insensitive
 * lookups, while preserving the original casing for output.
 *
 * This is an immutable value object. All mutation methods return
 * a new instance, leaving the original unchanged.
 *
 * Used by both Request and Response to avoid duplicated header logic.
 */
final class HeaderBag
{
    /**
     * Headers stored as lowercase-key => string[].
     *
     * @var array<string, string[]>
     */
    private readonly array $headers;

    /**
     * Map of lowercase key => original case key (first seen).
     *
     * @var array<string, string>
     */
    private readonly array $originalKeys;

    /**
     * @param array<string, string|string[]> $headers
     */
    public function __construct(array $headers = [])
    {
        $normalized = [];
        $originalKeys = [];

        foreach ($headers as $name => $value) {
            $values = is_array($value) ? $value : [$value];
            self::validate($name, $values);

            $lower = strtolower($name);

            if (!isset($originalKeys[$lower])) {
                $originalKeys[$lower] = $name;
            }

            $normalized[$lower] = array_merge($normalized[$lower] ?? [], $values);
        }

        $this->headers = $normalized;
        $this->originalKeys = $originalKeys;
    }

    /**
     * Get all headers.
     *
     * Returns headers keyed by their original casing.
     *
     * @return array<string, string[]>
     */
    public function all(): array
    {
        $result = [];

        foreach ($this->headers as $lower => $values) {
            $key = $this->originalKeys[$lower];
            $result[$key] = $values;
        }

        return $result;
    }

    /**
     * Get a single header value by name (case-insensitive).
     *
     * Returns the first value if multiple values exist,
     * or null if the header does not exist.
     */
    public function get(string $name): ?string
    {
        $lower = strtolower($name);

        if (!isset($this->headers[$lower])) {
            return null;
        }

        return $this->headers[$lower][0];
    }

    /**
     * Check if a header exists (case-insensitive).
     */
    public function has(string $name): bool
    {
        return isset($this->headers[strtolower($name)]);
    }

    /**
     * Get all values for a header (case-insensitive).
     *
     * @return string[]
     */
    public function getAll(string $name): array
    {
        return $this->headers[strtolower($name)] ?? [];
    }

    /**
     * Return a new HeaderBag with the given header set.
     *
     * Replaces any existing header with the same name.
     */
    public function withHeader(string $name, string|array $value): self
    {
        $values = is_array($value) ? $value : [$value];
        self::validate($name, $values);

        $headers = $this->toConstructorFormat();

        // Remove existing header with same lowercase key
        foreach ($headers as $existing => $v) {
            if (strtolower($existing) === strtolower($name)) {
                unset($headers[$existing]);
            }
        }

        $headers[$name] = $values;

        return new self($headers);
    }

    /**
     * Return a new HeaderBag without the given header.
     */
    public function withoutHeader(string $name): self
    {
        $headers = $this->toConstructorFormat();

        foreach ($headers as $existing => $v) {
            if (strtolower($existing) === strtolower($name)) {
                unset($headers[$existing]);
            }
        }

        return new self($headers);
    }

    /**
     * Get the number of headers.
     */
    public function count(): int
    {
        return count($this->headers);
    }

    /**
     * Check if the header bag is empty.
     */
    public function isEmpty(): bool
    {
        return $this->headers === [];
    }

    /**
     * Validate header name and value according to RFC 7230.
     *
     * @throws \InvalidArgumentException If name or value is invalid.
     */
    private static function validate(string $name, array $values): void
    {
        if ($name === '' || !preg_match('/^[a-zA-Z0-9\!#\$%&\'\*\+\-\.\^\_\`\|\~]+$/', $name)) {
            throw new \InvalidArgumentException(sprintf('Invalid header name: "%s"', $name));
        }

        foreach ($values as $value) {
            // RFC 7230 field-value character set: HTAB, SP, VCHAR, and
            // obs-text. CR, LF, NUL, and other disallowed C0 control
            // characters are rejected to prevent header injection and
            // response splitting.
            if (preg_match('/[^\x09\x20-\x7E\x80-\xFF]/', $value) === 1) {
                throw new \InvalidArgumentException(
                    'Header value contains disallowed control characters (CR, LF, NUL, etc.).'
                );
            }
        }
    }

    /**
     * Reconstruct the headers in original-key => values format
     * suitable for passing to the constructor.
     *
     * @return array<string, string[]>
     */
    private function toConstructorFormat(): array
    {
        $result = [];

        foreach ($this->headers as $lower => $values) {
            $key = $this->originalKeys[$lower];
            $result[$key] = $values;
        }

        return $result;
    }
}
