<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Database;

use FlintPHP\Framework\Config\Contract\ConfigRepositoryInterface;
use FlintPHP\Framework\Foundation\Application;
use FlintPHP\Framework\Foundation\BootstrapperInterface;
use Psr\Container\ContainerInterface;
use TypeError;

/**
 * Bootstraps the Database component into the Application.
 *
 * Registers a lazy singleton factory for ConnectionInterface.
 */
final class DatabaseBootstrapper implements BootstrapperInterface
{
    public function bootstrap(Application $app): void
    {
        $app->container()->singleton(ConnectionInterface::class, function (ContainerInterface $c) {
            $configRepo = $c->get(ConfigRepositoryInterface::class);
            $dbConfig = $configRepo->get('database');

            // Do not silently coerce null or scalar configurations.
            if (!is_array($dbConfig)) {
                throw new TypeError(sprintf(
                    'Database configuration must be an array, got %s.',
                    get_debug_type($dbConfig)
                ));
            }

            return ConnectionFactory::make($dbConfig);
        });
    }
}
