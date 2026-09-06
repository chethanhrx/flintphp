<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Validation;

use FlintPHP\Framework\Foundation\Application;
use FlintPHP\Framework\Foundation\BootstrapperInterface;

/**
 * Bootstraps the Validation component into the Application.
 *
 * Registers the Validator as a lazy container singleton. Singleton
 * registration matters here: the Validator is stateless by itself, but
 * custom rules registered via Validator::withRule() produce a new Validator
 * instance — registering the configured instance as a singleton ensures every
 * consumer resolved through the Container sees the same rule configuration.
 *
 * Custom rules are registered explicitly by the application:
 *
 *     $app->container()->singleton(Validator::class, function () {
 *         return (new Validator())->withRule('phone', new PhoneRule());
 *     });
 *
 * The bootstrapper itself registers only the pristine base Validator.
 * No global validator, no automatic request validation, no rule discovery.
 */
final class ValidationBootstrapper implements BootstrapperInterface
{
    public function bootstrap(Application $app): void
    {
        $app->container()->singleton(Validator::class, static function (): Validator {
            return new Validator();
        });
    }
}
