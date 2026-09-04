<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Foundation;

use RuntimeException;

/**
 * The FlintPHP Application.
 *
 * This is the central entry point for the framework. It coordinates
 * the bootstrap process and holds the application's base path.
 *
 * Design philosophy:
 * - Thin coordinator, NOT a god object or service locator.
 * - Components (Container, Router, Kernel) will be composed into
 *   this class in later patches, not baked in from day one.
 * - Final class: extending Application is an anti-pattern.
 *   Use composition and the container instead.
 */
final class Application
{
    private bool $booted = false;

    /**
     * Create a new FlintPHP application instance.
     *
     * @param string $basePath The root directory of the application.
     */
    public function __construct(
        private readonly string $basePath,
    ) {
    }

    /**
     * Boot the application.
     *
     * Marks the application as booted. In future patches, this will
     * register service providers, compile the container, and prepare
     * the kernel.
     *
     * @throws RuntimeException If the application has already been booted.
     */
    public function boot(): void
    {
        if ($this->booted) {
            throw new RuntimeException('Application has already been booted.');
        }

        $this->booted = true;
    }

    /**
     * Determine if the application has been booted.
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * Get the base path of the application.
     *
     * Returns the path with trailing directory separators removed.
     */
    public function basePath(): string
    {
        return rtrim($this->basePath, DIRECTORY_SEPARATOR);
    }

    /**
     * Get the framework version.
     */
    public function version(): string
    {
        return FlintPHP::VERSION;
    }
}
