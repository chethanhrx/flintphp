<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Foundation;

use FlintPHP\Framework\Config\ConfigRepository;
use FlintPHP\Framework\Config\Contract\ConfigRepositoryInterface;
use FlintPHP\Framework\Container\Container;

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
    private readonly ConfigRepositoryInterface $config;
    private readonly Container $container;

    /**
     * Create a new FlintPHP application instance.
     *
     * @param string $basePath The root directory of the application.
     * @param ConfigRepositoryInterface|null $config Explicit configuration repository.
     */
    public function __construct(
        private readonly string $basePath,
        ?ConfigRepositoryInterface $config = null,
    ) {
        // Initialize explicit or sensible default instances
        $this->config = $config ?? new ConfigRepository([]);
        $this->container = new Container();

        // Deterministic Bootstrap Order:
        // Registration is performed during construction rather than boot()
        // to ensure the container is fully formed and the configuration is
        // immediately available to any components resolved before boot.
        $this->container->singleton(ConfigRepositoryInterface::class, $this->config);
    }

    /**
     * Get the application's dependency injection container.
     */
    public function container(): Container
    {
        return $this->container;
    }

    /**
     * Get the application's configuration repository.
     */
    public function config(): ConfigRepositoryInterface
    {
        return $this->config;
    }

    /**
     * Boot the application.
     *
     * Marks the application as booted. In future patches, this will
     * register service providers, compile the container, and prepare
     * the kernel.
     *
     * This method is idempotent — calling it multiple times is safe.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
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
     * Returns the path with trailing directory separators removed,
     * preserving the root path '/' on Unix systems.
     */
    public function basePath(): string
    {
        return rtrim($this->basePath, DIRECTORY_SEPARATOR) ?: DIRECTORY_SEPARATOR;
    }

    /**
     * Get the framework version.
     */
    public function version(): string
    {
        return FlintPHP::VERSION;
    }
}
