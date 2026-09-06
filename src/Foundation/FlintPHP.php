<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Foundation;

/**
 * Framework metadata and version information.
 *
 * Single source of truth for FlintPHP identity constants.
 * Avoid scattering version strings across the codebase.
 */
final class FlintPHP
{
    /**
     * The framework name.
     */
    public const NAME = 'FlintPHP';

    /**
     * The framework version.
     */
    public const VERSION = '0.20.0';

    /**
     * Get the framework name.
     */
    public static function name(): string
    {
        return self::NAME;
    }

    /**
     * Get the framework version.
     */
    public static function version(): string
    {
        return self::VERSION;
    }
}
