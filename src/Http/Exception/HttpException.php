<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Http\Exception;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Represents a controlled HTTP error response.
 *
 * This exception intentionally communicates a specific HTTP status code
 * and an optional public message that is safe to expose to clients.
 */
final class HttpException extends RuntimeException
{
    private readonly int $status;

    public function __construct(int $status, string $message = '', ?Throwable $previous = null)
    {
        if ($status < 100 || $status > 599) {
            throw new InvalidArgumentException(sprintf('Invalid HTTP status code: %d', $status));
        }

        $this->status = $status;

        parent::__construct($message, 0, $previous);
    }

    /**
     * Get the HTTP status code.
     */
    public function status(): int
    {
        return $this->status;
    }
}
