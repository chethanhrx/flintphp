<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Authentication;

use FlintPHP\Framework\Authentication\Middleware\RequireAuthenticationMiddleware;
use FlintPHP\Framework\Foundation\Application;
use FlintPHP\Framework\Foundation\BootstrapperInterface;
use Psr\Container\ContainerInterface;

/**
 * Bootstraps the Authentication component into the Application.
 *
 * Registers lazy singletons for:
 * - AuthenticatorInterface: defaults to BearerTokenAuthenticator.
 * - RequireAuthenticationMiddleware: resolvable as route-scoped middleware.
 *
 * Contract:
 * - The developer MUST explicitly bind their own UserProviderInterface
 *   implementation (e.g. via a bootstrapper that runs before this one, or a
 *   direct container binding). The provider is intentionally NOT coupled to
 *   the ORM and no User model or users table is assumed. If no provider is
 *   bound, the first authentication attempt fails with a Container
 *   NotFoundException — a deliberate fail-loud signal, not a silent fallback.
 * - To use a fully custom AuthenticatorInterface implementation, rebind
 *   AuthenticatorInterface after bootstrapping; the default
 *   BearerTokenAuthenticator is only a lazy default and is never instantiated
 *   unless resolved.
 */
final class AuthenticationBootstrapper implements BootstrapperInterface
{
    public function bootstrap(Application $app): void
    {
        $app->container()->singleton(AuthenticatorInterface::class, function (ContainerInterface $c) {
            return new BearerTokenAuthenticator(
                $c->get(UserProviderInterface::class)
            );
        });

        $app->container()->singleton(RequireAuthenticationMiddleware::class, function (ContainerInterface $c) {
            return new RequireAuthenticationMiddleware(
                $c->get(AuthenticatorInterface::class)
            );
        });
    }
}
