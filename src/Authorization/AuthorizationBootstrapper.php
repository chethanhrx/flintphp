<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Authorization;

use FlintPHP\Framework\Authorization\Middleware\RequireAuthorizationMiddleware;
use FlintPHP\Framework\Foundation\Application;
use FlintPHP\Framework\Foundation\BootstrapperInterface;
use Psr\Container\ContainerInterface;

/**
 * Bootstraps the Authorization component into the Application.
 *
 * Registers a lazy singleton for RequireAuthorizationMiddleware so it can be
 * resolved through the Container (route-scoped middleware list, or global
 * registration via Application::addMiddleware()).
 *
 * Contract:
 * - The framework does NOT register AuthorizerInterface. The application MUST
 *   explicitly bind its own implementation (the contract has no default, not
 *   even deny-all, so misconfiguration fails loudly instead of silently). If
 *   no implementation is bound, the first protected request fails with a
 *   container NotFoundException inside the Kernel's exception boundary —
 *   fail-closed, never fail-open.
 * - To change the required ability, register the middleware under a custom
 *   container id with a preconfigured ability, or subclass it. The default
 *   binding forwards the empty ability ('' = overall route access).
 * - Re-running this bootstrapper overwrites the middleware binding and drops
 *   any cached instance — redundant but safe, consistent with the other
 *   framework bootstrappers.
 * - Application isolation: all bindings are instance-scoped per Application
 *   container; no static or global authorization state exists.
 */
final class AuthorizationBootstrapper implements BootstrapperInterface
{
    public function bootstrap(Application $app): void
    {
        $app->container()->singleton(RequireAuthorizationMiddleware::class, function (ContainerInterface $c) {
            return new RequireAuthorizationMiddleware(
                $c->get(AuthorizerInterface::class)
            );
        });
    }
}
