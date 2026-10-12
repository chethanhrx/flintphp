<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Foundation;

use FlintPHP\Framework\Config\ConfigRepository;
use FlintPHP\Framework\Config\Contract\ConfigRepositoryInterface;
use FlintPHP\Framework\Container\Container;
use FlintPHP\Framework\Http\Exception\ExceptionHandler;
use FlintPHP\Framework\Http\Exception\ExceptionHandlerInterface;
use FlintPHP\Framework\Http\Kernel;
use FlintPHP\Framework\Middleware\MiddlewareStack;
use FlintPHP\Framework\Routing\HandlerInvoker;
use FlintPHP\Framework\Routing\Router;
use LogicException;

/**
 * The FlintPHP Application.
 *
 * This is the central entry point for the framework. It coordinates
 * the bootstrap process and holds the application's base path.
 *
 * Design philosophy:
 * - Thin coordinator, NOT a god object or service locator.
 * - Explicit HTTP composition root: inherently owns and registers the core
 *   HTTP foundation (Container, Router, Kernel, MiddlewareStack, ExceptionHandler).
 * - Extensible via single-phase bootstrappers, avoiding complex lifecycle magic.
 * - Final class: extending Application is an anti-pattern.
 *   Use composition and the container instead.
 */
final class Application
{
    private bool $booted = false;
    private readonly ConfigRepositoryInterface $config;
    private readonly Container $container;
    private readonly Router $router;
    private ?MiddlewareStack $middlewareStack = null;
    private ExceptionHandlerInterface $exceptionHandler;
    private ?Kernel $kernel = null;

    /** @var string[] */
    private array $globalMiddlewares = [];
    private bool $middlewareStackFrozen = false;

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
        $this->config = $config ?? new ConfigRepository([]);
        $this->container = new Container();

        $this->container->singleton(ConfigRepositoryInterface::class, $this->config);

        $this->router = new Router();
        $invoker = new HandlerInvoker($this->container);
        $this->exceptionHandler = new ExceptionHandler();

        $this->container->singleton(Router::class, $this->router);
        $this->container->singleton(HandlerInvoker::class, $invoker);
        $this->container->singleton(ExceptionHandlerInterface::class, $this->exceptionHandler);

        $this->container->singleton(MiddlewareStack::class, function () {
            return $this->middleware();
        });

        $this->container->singleton(Kernel::class, function () {
            return $this->kernel();
        });
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function config(): ConfigRepositoryInterface
    {
        return $this->config;
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function middleware(): MiddlewareStack
    {
        if ($this->middlewareStack === null) {
            $this->middlewareStackFrozen = true;

            $resolvedMiddlewares = [];
            foreach ($this->globalMiddlewares as $middlewareClass) {
                $middleware = $this->container->get($middlewareClass);

                if (!$middleware instanceof \FlintPHP\Framework\Middleware\MiddlewareInterface) {
                    throw new \InvalidArgumentException(sprintf(
                        'Middleware %s must implement %s.',
                        $middlewareClass,
                        \FlintPHP\Framework\Middleware\MiddlewareInterface::class
                    ));
                }

                $resolvedMiddlewares[] = $middleware;
            }

            $this->middlewareStack = new MiddlewareStack($resolvedMiddlewares);
        }

        return $this->middlewareStack;
    }

    public function kernel(): Kernel
    {
        if ($this->kernel === null) {
            $this->kernel = new Kernel(
                $this->router,
                $this->middleware(),
                $this->container->get(HandlerInvoker::class),
                $this->exceptionHandler,
                $this->container
            );
        }

        return $this->kernel;
    }

    /**
     * Register a global middleware class.
     *
     * @throws LogicException If the HTTP pipeline has already been finalized.
     */
    public function addMiddleware(string $middlewareClass): void
    {
        if ($this->middlewareStackFrozen) {
            throw new LogicException('Cannot add middleware after the HTTP pipeline has been finalized.');
        }

        $this->globalMiddlewares[] = $middlewareClass;
    }

    public function exceptionHandler(): ExceptionHandlerInterface
    {
        return $this->exceptionHandler;
    }

    /**
     * Replace the exception handler before the Kernel is built.
     *
     * Typical use for API apps:
     *   $app->setExceptionHandler(new JsonExceptionHandler());
     *
     * @throws LogicException If kernel() has already been resolved.
     */
    public function setExceptionHandler(ExceptionHandlerInterface $handler): void
    {
        if ($this->kernel !== null) {
            throw new LogicException(
                'Cannot replace the exception handler after the Kernel has been finalized.'
            );
        }

        $this->exceptionHandler = $handler;
        $this->container->singleton(ExceptionHandlerInterface::class, $handler);
    }

    /**
     * @param array $bootstrappers An array of BootstrapperInterface instances.
     *
     * @throws \InvalidArgumentException If an element is not a BootstrapperInterface.
     */
    public function bootstrapWith(array $bootstrappers): void
    {
        foreach ($bootstrappers as $bootstrapper) {
            if (!$bootstrapper instanceof BootstrapperInterface) {
                throw new \InvalidArgumentException(sprintf(
                    'Application::bootstrapWith() expects an array of %s instances. Got: %s',
                    BootstrapperInterface::class,
                    get_debug_type($bootstrapper)
                ));
            }

            $bootstrapper->bootstrap($this);
        }
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }

    public function basePath(): string
    {
        return rtrim($this->basePath, DIRECTORY_SEPARATOR) ?: DIRECTORY_SEPARATOR;
    }

    public function version(): string
    {
        return FlintPHP::VERSION;
    }
}
