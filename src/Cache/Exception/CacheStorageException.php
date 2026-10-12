<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Cache\Exception;

use RuntimeException;

/**
 * Thrown when an underlying cache storage or locking operation fails.
 */
class CacheStorageException extends RuntimeException implements CacheException
{
}
