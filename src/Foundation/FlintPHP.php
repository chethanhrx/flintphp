<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Foundation;

/**
 * Framework metadata and version information.
 */
final class FlintPHP
{
    public const NAME = 'FlintPHP';

    public const VERSION = '1.1.0';

    public static function name(): string
    {
        return self::NAME;
    }

    public static function version(): string
    {
        return self::VERSION;
    }
}
