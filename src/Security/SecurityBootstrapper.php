<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Security;

use FlintPHP\Framework\Config\Contract\ConfigRepositoryInterface;
use FlintPHP\Framework\Config\Exception\ConfigurationException;
use FlintPHP\Framework\Foundation\Application;
use FlintPHP\Framework\Foundation\BootstrapperInterface;
use FlintPHP\Framework\Security\Http\TrustedProxyConfiguration;
use FlintPHP\Framework\Security\Middleware\SecurityHeadersConfiguration;
use FlintPHP\Framework\Security\Middleware\SecurityHeadersMiddleware;
use Psr\Container\ContainerInterface;

/**
 * Bootstraps the Security component into the Application.
 *
 * Registers lazy singletons for security configurations and middleware.
 */
final class SecurityBootstrapper implements BootstrapperInterface
{
    public function bootstrap(Application $app): void
    {
        $app->container()->singleton(SecurityHeadersConfiguration::class, function (ContainerInterface $c) {
            $config = $c->get(ConfigRepositoryInterface::class);
            $headersConfig = $config->get('security.headers');

            if ($headersConfig !== null && !is_array($headersConfig)) {
                throw new ConfigurationException('security.headers must be an array or null.');
            }

            $headersConfig = $headersConfig ?? [];

            return new SecurityHeadersConfiguration(
                $headersConfig['x_content_type_options'] ?? 'nosniff',
                $headersConfig['x_frame_options'] ?? 'DENY',
                $headersConfig['referrer_policy'] ?? 'strict-origin-when-cross-origin',
                $headersConfig['content_security_policy'] ?? null,
                $headersConfig['strict_transport_security'] ?? null
            );
        });

        $app->container()->singleton(SecurityHeadersMiddleware::class, function (ContainerInterface $c) {
            return new SecurityHeadersMiddleware($c->get(SecurityHeadersConfiguration::class));
        });

        $app->container()->singleton(TrustedProxyConfiguration::class, function (ContainerInterface $c) {
            $config = $c->get(ConfigRepositoryInterface::class);
            $proxies = $config->get('security.proxies');

            if ($proxies !== null && !is_array($proxies)) {
                throw new ConfigurationException('security.proxies must be an array or null.');
            }

            $proxies = $proxies ?? [];

            return new TrustedProxyConfiguration($proxies);
        });

        $app->addMiddleware(SecurityHeadersMiddleware::class);
    }
}
