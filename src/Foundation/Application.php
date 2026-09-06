<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Foundation;

use FlintPHP\Framework\Config\ConfigRepository;
use FlintPHP\Framework\Config\Contract\ConfigRepositoryInterface;
use FlintPHP\Framework\Container\Container;
use FlintPHP\Framework\Http\Kernel;
use FlintPHP\Framework\Middleware\MiddlewareStack;
use FlintPHP\Framework\Routing\HandlerInvoker;
use FlintPHP\Framework\Routing\Router;

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
    private readonly Router $router;
    private readonly MiddlewareStack $middlewareStack;
    private readonly Kernel $kernel;

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

        // 1. Configuration Registration
        $this->container->singleton(ConfigRepositoryInterface::class, $this->config);

        // 2. HTTP Runtime Composition
        $this->router = new Router();
        $this->middlewareStack = new MiddlewareStack();
        $invoker = new HandlerInvoker($this->container);
        $this->kernel = new Kernel($this->router, $this->middlewareStack, $invoker);

        // 3. Register HTTP Runtime Components into the Container
        $this->container->singleton(Router::class, $this->router);
        $this->container->singleton(MiddlewareStack::class, $this->middlewareStack);
        $this->container->singleton(HandlerInvoker::class, $invoker);
        $this->container->singleton(Kernel::class, $this->kernel);
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
     * Get the HTTP router.
     */
    public function router(): Router
    {
        return $this->router;
    }

    /**
     * Get the HTTP middleware stack.
     */
    public function middleware(): MiddlewareStack
    {
        return $this->middlewareStack;
    }

    /**
     * Get the HTTP kernel.
     */
    public function kernel(): Kernel
    {
        return $this->kernel;
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
