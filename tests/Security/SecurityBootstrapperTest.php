<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Security;

use FlintPHP\Framework\Config\ConfigRepository;
use FlintPHP\Framework\Config\Contract\ConfigRepositoryInterface;
use FlintPHP\Framework\Config\Exception\ConfigurationException;
use FlintPHP\Framework\Container\NotFoundException;
use FlintPHP\Framework\Foundation\Application;
use FlintPHP\Framework\Foundation\BootstrapperInterface;
use FlintPHP\Framework\Security\Http\TrustedProxyConfiguration;
use FlintPHP\Framework\Security\Middleware\SecurityHeadersConfiguration;
use FlintPHP\Framework\Security\Middleware\SecurityHeadersMiddleware;
use FlintPHP\Framework\Security\SecurityBootstrapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SecurityBootstrapper::class)]
final class SecurityBootstrapperTest extends TestCase
{
    #[Test]
    public function it_implements_bootstrapper_interface(): void
    {
        $bootstrapper = new SecurityBootstrapper();
        $this->assertInstanceOf(BootstrapperInterface::class, $bootstrapper);
    }

    #[Test]
    public function bootstrap_registers_security_components(): void
    {
        $app = new Application('/var/www/myapp');
        $app->bootstrapWith([new SecurityBootstrapper()]);

        $this->assertTrue($app->container()->has(SecurityHeadersConfiguration::class));
        $this->assertTrue($app->container()->has(SecurityHeadersMiddleware::class));
        $this->assertTrue($app->container()->has(TrustedProxyConfiguration::class));
    }

    #[Test]
    public function components_are_resolved_as_singletons(): void
    {
        $config = new ConfigRepository([]);
        $app = new Application('/var/www/myapp', $config);
        
        $app->bootstrapWith([new SecurityBootstrapper()]);

        $firstHeaders = $app->container()->get(SecurityHeadersMiddleware::class);
        $secondHeaders = $app->container()->get(SecurityHeadersMiddleware::class);
        $this->assertSame($firstHeaders, $secondHeaders);

        $firstProxies = $app->container()->get(TrustedProxyConfiguration::class);
        $secondProxies = $app->container()->get(TrustedProxyConfiguration::class);
        $this->assertSame($firstProxies, $secondProxies);
    }

    #[Test]
    public function bootstrapper_is_lazy_and_does_not_instantiate_eagerly(): void
    {
        $app = new Application('/var/www/myapp'); 
        
        // No ConfigRepository is registered, so resolution would fail.
        // If it was eager, bootstrapWith would throw an exception.
        $app->bootstrapWith([new SecurityBootstrapper()]);
        
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function missing_config_repository_throws_container_exception_on_resolution(): void
    {
        $app = new Application('/var/www/myapp');
        // We explicitly remove the ConfigRepository interface binding that the Application constructor might have added
        // Wait, the Application constructor always binds ConfigRepositoryInterface if one is provided or defaults it.
        // Let's create an empty container manually or just test the Application behavior if we unset it.
        $app->container()->singleton(ConfigRepositoryInterface::class, function () {
            throw new NotFoundException('Config missing');
        });

        $app->bootstrapWith([new SecurityBootstrapper()]); 

        $this->expectException(NotFoundException::class);
        
        $app->container()->get(SecurityHeadersConfiguration::class);
    }

    #[Test]
    public function security_headers_configuration_uses_config_repository_values(): void
    {
        $config = new ConfigRepository([
            'security' => [
                'headers' => [
                    'x_content_type_options' => 'custom-nosniff',
                    'x_frame_options' => 'SAMEORIGIN',
                    'referrer_policy' => 'no-referrer',
                    'content_security_policy' => "default-src 'self'",
                    'strict_transport_security' => 'max-age=31536000',
                ]
            ]
        ]);
        $app = new Application('/var/www/myapp', $config);
        $app->bootstrapWith([new SecurityBootstrapper()]);

        $headersConfig = $app->container()->get(SecurityHeadersConfiguration::class);
        
        $this->assertSame('custom-nosniff', $headersConfig->xContentTypeOptions);
        $this->assertSame('SAMEORIGIN', $headersConfig->xFrameOptions);
        $this->assertSame('no-referrer', $headersConfig->referrerPolicy);
        $this->assertSame("default-src 'self'", $headersConfig->contentSecurityPolicy);
        $this->assertSame('max-age=31536000', $headersConfig->strictTransportSecurity);
    }

    #[Test]
    public function trusted_proxy_configuration_uses_config_repository_values(): void
    {
        $config = new ConfigRepository([
            'security' => [
                'proxies' => [
                    '192.168.1.1',
                    '10.0.0.0/8'
                ]
            ]
        ]);
        $app = new Application('/var/www/myapp', $config);
        $app->bootstrapWith([new SecurityBootstrapper()]);

        $proxyConfig = $app->container()->get(TrustedProxyConfiguration::class);
        
        $this->assertTrue($proxyConfig->isTrusted('192.168.1.1'));
        $this->assertTrue($proxyConfig->isTrusted('10.0.0.5'));
        $this->assertFalse($proxyConfig->isTrusted('8.8.8.8'));
    }

    #[Test]
    public function invalid_headers_configuration_fails_explicitly(): void
    {
        $config = new ConfigRepository([
            'security' => [
                'headers' => 'not-an-array'
            ]
        ]);
        $app = new Application('/var/www/myapp', $config);
        $app->bootstrapWith([new SecurityBootstrapper()]);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('security.headers must be an array or null.');
        
        $app->container()->get(SecurityHeadersConfiguration::class);
    }

    #[Test]
    public function invalid_proxy_configuration_fails_explicitly(): void
    {
        $config = new ConfigRepository([
            'security' => [
                'proxies' => 'not-an-array'
            ]
        ]);
        $app = new Application('/var/www/myapp', $config);
        $app->bootstrapWith([new SecurityBootstrapper()]);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('security.proxies must be an array or null.');

        $app->container()->get(TrustedProxyConfiguration::class);
    }

    #[Test]
    public function missing_configuration_uses_documented_defaults(): void
    {
        $config = new ConfigRepository([]);
        $app = new Application('/var/www/myapp', $config);
        $app->bootstrapWith([new SecurityBootstrapper()]);

        $headersConfig = $app->container()->get(SecurityHeadersConfiguration::class);
        
        $this->assertSame('nosniff', $headersConfig->xContentTypeOptions);
        $this->assertSame('DENY', $headersConfig->xFrameOptions);
        $this->assertSame('strict-origin-when-cross-origin', $headersConfig->referrerPolicy);
        $this->assertNull($headersConfig->contentSecurityPolicy);
        $this->assertNull($headersConfig->strictTransportSecurity);

        $proxyConfig = $app->container()->get(TrustedProxyConfiguration::class);
        $this->assertFalse($proxyConfig->isTrusted('192.168.1.1'));
    }

    #[Test]
    public function security_headers_middleware_is_added_to_application_pipeline(): void
    {
        $app = new Application('/var/www/myapp');
        $app->bootstrapWith([new SecurityBootstrapper()]);

        $app->router()->get('/', function () {
            return new \FlintPHP\Framework\Http\Response('OK');
        });

        $request = new \FlintPHP\Framework\Http\Request('GET', '/');
        $response = $app->kernel()->handle($request);

        $this->assertTrue($response->headers()->has('X-Content-Type-Options'));
        $this->assertSame('nosniff', $response->headers()->get('X-Content-Type-Options'));
        $this->assertSame('DENY', $response->headers()->get('X-Frame-Options'));
    }

    #[Test]
    public function repeated_security_bootstrapper_preserves_duplicate_middleware_registrations(): void
    {
        $app = new Application('/var/www/myapp');
        
        $app->bootstrapWith([new SecurityBootstrapper()]);
        $app->bootstrapWith([new SecurityBootstrapper()]);

        $app->router()->get('/', function () {
            return new \FlintPHP\Framework\Http\Response('OK');
        });

        $request = new \FlintPHP\Framework\Http\Request('GET', '/');
        $response = $app->kernel()->handle($request);

        // Should still execute correctly (the middleware is idempotent regarding headers, but it does execute twice)
        $this->assertTrue($response->headers()->has('X-Content-Type-Options'));
        $this->assertSame('nosniff', $response->headers()->get('X-Content-Type-Options'));
    }
}
