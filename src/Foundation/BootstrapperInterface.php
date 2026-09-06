<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Foundation;

/**
 * Encapsulates application composition logic.
 *
 * Bootstrappers are explicit, single-phase configurators that execute
 * sequentially. They receive the Application instance to register services,
 * configure routes, or wire middleware.
 */
interface BootstrapperInterface
{
    /**
     * Bootstrap the given application instance.
     *
     * @param Application $app The application instance being bootstrapped.
     */
    public function bootstrap(Application $app): void;
}
