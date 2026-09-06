<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Orm;

use FlintPHP\Framework\Database\ConnectionInterface;
use FlintPHP\Framework\Foundation\Application;
use FlintPHP\Framework\Foundation\BootstrapperInterface;
use Psr\Container\ContainerInterface;

/**
 * Bootstraps the ORM component into the Application.
 *
 * Registers a lazy singleton factory for OrmManager.
 */
final class OrmBootstrapper implements BootstrapperInterface
{
    public function bootstrap(Application $app): void
    {
        $app->container()->singleton(OrmManager::class, function (ContainerInterface $c) {
            return new OrmManager($c->get(ConnectionInterface::class));
        });
    }
}
