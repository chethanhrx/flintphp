<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Cache\Exception;

use InvalidArgumentException as BaseInvalidArgumentException;

/**
 * Thrown when a cache key is invalid.
 */
class InvalidArgumentException extends BaseInvalidArgumentException implements CacheException
{
}
