<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Cache\Exception;

use RuntimeException;

/**
 * Thrown when a value cannot be serialized for the cache.
 */
class InvalidCacheValueException extends RuntimeException implements CacheException
{
}
