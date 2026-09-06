<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Foundation;

use FlintPHP\Framework\Config\ConfigRepository;
use FlintPHP\Framework\Config\Contract\ConfigRepositoryInterface;
use FlintPHP\Framework\Container\Container;
use FlintPHP\Framework\Foundation\Application;
use FlintPHP\Framework\Foundation\FlintPHP;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Application::class)]
final class ApplicationTest extends TestCase
{
    // ── Backward Compatibility & Paths ──

    #[Test]
    public function it_can_be_instantiated_with_a_base_path(): void
    {
        $app = new Application('/var/www/myapp');

        $this->assertInstanceOf(Application::class, $app);
    }

    #[Test]
    public function it_returns_the_base_path(): void
    {
        $app = new Application('/var/www/myapp');

        $this->assertSame('/var/www/myapp', $app->basePath());
    }

    #[Test]
    public function it_strips_trailing_separator_from_base_path(): void
    {
        $app = new Application('/var/www/myapp/');

        $this->assertSame('/var/www/myapp', $app->basePath());
    }

    #[Test]
    public function it_strips_multiple_trailing_separators_from_base_path(): void
    {
        $app = new Application('/var/www/myapp///');

        $this->assertSame('/var/www/myapp', $app->basePath());
    }

    #[Test]
    public function it_preserves_root_path(): void
    {
        $app = new Application('/');

        $this->assertSame('/', $app->basePath());
    }

    #[Test]
    public function it_returns_the_framework_version(): void
    {
        $app = new Application('/var/www/myapp');

        $this->assertSame(FlintPHP::VERSION, $app->version());
    }

    // ── Construction ──

    #[Test]
    public function existing_one_argument_constructor_remains_valid(): void
    {
        $app = new Application('/var/www/myapp');
        $this->assertInstanceOf(Application::class, $app);
    }

    #[Test]
    public function it_works_with_empty_configuration_repository(): void
    {
        // When not explicitly passed, a sensible empty config is used
        $app = new Application('/var/www/myapp');

        $this->assertInstanceOf(ConfigRepositoryInterface::class, $app->config());
        $this->assertEmpty($app->config()->all());
    }

    #[Test]
    public function explicit_configuration_works_and_repository_identity_is_preserved(): void
    {
        $config = new ConfigRepository(['app' => ['name' => 'FlintPHP']]);

        $app = new Application('/var/www/myapp', $config);

        $this->assertSame($config, $app->config());
    }

    // ── Container ──

    #[Test]
    public function application_exposes_its_container(): void
    {
        $app = new Application('/var/www/myapp');

        $this->assertInstanceOf(Container::class, $app->container());
    }

    #[Test]
    public function container_is_instance_scoped(): void
    {
        $app1 = new Application('/var/www/myapp');
        $app2 = new Application('/var/www/myapp');

        $this->assertNotSame($app1->container(), $app2->container());
    }

    #[Test]
    public function configured_repository_is_registered_and_resolves_to_exact_instance(): void
    {
        $config = new ConfigRepository(['env' => 'testing']);
        $app = new Application('/var/www/myapp', $config);

        $resolved = $app->container()->get(ConfigRepositoryInterface::class);

        $this->assertSame($config, $resolved);
    }

    // ── Configuration ──

    #[Test]
    public function configuration_returns_expected_data(): void
    {
        $config = new ConfigRepository(['app' => ['name' => 'FlintPHP']]);
        $app = new Application('/var/www/myapp', $config);

        $this->assertSame('FlintPHP', $app->config()->get('app.name'));
    }

    // ── Boot ──

    #[Test]
    public function it_is_not_booted_before_boot_is_called(): void
    {
        $app = new Application('/var/www/myapp');

        $this->assertFalse($app->isBooted());
    }

    #[Test]
    public function it_is_booted_after_boot_is_called(): void
    {
        $app = new Application('/var/www/myapp');
        $app->boot();

        $this->assertTrue($app->isBooted());
    }

    #[Test]
    public function repeated_boot_is_idempotent(): void
    {
        $config = new ConfigRepository([]);
        $app = new Application('/var/www/myapp', $config);

        $app->boot();

        $containerBefore = $app->container();
        $configBefore = $app->config();

        $app->boot();
        $app->boot();

        $this->assertTrue($app->isBooted());
        // does not replace container
        $this->assertSame($containerBefore, $app->container());
        // does not replace config
        $this->assertSame($configBefore, $app->config());
        // does not duplicate registration (resolves to same instance)
        $this->assertSame($config, $app->container()->get(ConfigRepositoryInterface::class));
    }

    // ── Isolation ──

    #[Test]
    public function applications_are_completely_isolated(): void
    {
        $appA = new Application('/var/www/appA', new ConfigRepository(['name' => 'App A']));
        $appB = new Application('/var/www/appB', new ConfigRepository(['name' => 'App B']));

        // different containers
        $this->assertNotSame($appA->container(), $appB->container());

        // different configuration repositories
        $this->assertNotSame($appA->config(), $appB->config());

        // no state leakage
        $this->assertSame('App A', $appA->config()->get('name'));
        $this->assertSame('App B', $appB->config()->get('name'));

        // booting A does not affect B
        $appA->boot();
        $this->assertTrue($appA->isBooted());
        $this->assertFalse($appB->isBooted());

        // booting B does not affect A
        $appB->boot();
        $this->assertTrue($appB->isBooted());
        $this->assertTrue($appA->isBooted());
    }
}
