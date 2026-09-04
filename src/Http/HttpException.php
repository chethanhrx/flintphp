<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Http;

use InvalidArgumentException;

/**
 * Exception for invalid HTTP state.
 *
 * Thrown when HTTP objects are constructed with invalid data
 * (e.g., status codes outside the valid range).
 *
 * Does not leak internal application details in its message.
 */
final class HttpException extends InvalidArgumentException
{
}
