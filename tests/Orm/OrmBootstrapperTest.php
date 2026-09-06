<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Orm;

use FlintPHP\Framework\Config\ConfigRepository;
use FlintPHP\Framework\Container\NotFoundException;
use FlintPHP\Framework\Database\ConnectionInterface;
use FlintPHP\Framework\Database\DatabaseBootstrapper;
use FlintPHP\Framework\Foundation\Application;
use FlintPHP\Framework\Foundation\BootstrapperInterface;
use FlintPHP\Framework\Orm\OrmBootstrapper;
use FlintPHP\Framework\Orm\OrmManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(OrmBootstrapper::class)]
final class OrmBootstrapperTest extends TestCase
{
    // 1. OrmBootstrapper registers OrmManager.
    #[Test]
    public function it_implements_bootstrapper_interface(): void
    {
        $bootstrapper = new OrmBootstrapper();
        $this->assertInstanceOf(BootstrapperInterface::class, $bootstrapper);
    }

    #[Test]
    public function bootstrap_registers_orm_manager(): void
    {
        $app = new Application('/var/www/myapp');
        $app->bootstrapWith([new OrmBootstrapper()]);

        $this->assertTrue($app->container()->has(OrmManager::class));
    }

    // 2. OrmManager resolves successfully when DatabaseBootstrapper is also registered.
    #[Test]
    public function orm_manager_resolves_successfully_when_database_is_registered(): void
    {
        $config = new ConfigRepository(['database' => ['driver' => 'sqlite', 'database' => ':memory:']]);
        $app = new Application('/var/www/myapp', $config);
        
        $app->bootstrapWith([
            new DatabaseBootstrapper(),
            new OrmBootstrapper(),
        ]);

        $manager = $app->container()->get(OrmManager::class);
        $this->assertInstanceOf(OrmManager::class, $manager);
    }

    // 3. OrmManager is a singleton.
    #[Test]
    public function orm_manager_is_a_singleton(): void
    {
        $config = new ConfigRepository(['database' => ['driver' => 'sqlite', 'database' => ':memory:']]);
        $app = new Application('/var/www/myapp', $config);
        
        $app->bootstrapWith([
            new DatabaseBootstrapper(),
            new OrmBootstrapper(),
        ]);

        $first = $app->container()->get(OrmManager::class);
        $second = $app->container()->get(OrmManager::class);

        $this->assertSame($first, $second);
    }

    // 4. OrmBootstrapper itself does not instantiate OrmManager during bootstrap().
    #[Test]
    public function bootstrapper_is_lazy_and_does_not_instantiate_orm_eagerly(): void
    {
        $app = new Application('/var/www/myapp'); // No config or database registered
        
        // This should not throw, proving resolution hasn't occurred
        $app->bootstrapWith([new OrmBootstrapper()]);
        
        $this->expectNotToPerformAssertions();
    }

    // 5. Application instances are isolated.
    // 11. No global/static ORM state is introduced.
    #[Test]
    public function application_instances_have_isolated_orm_managers(): void
    {
        $configA = new ConfigRepository(['database' => ['driver' => 'sqlite', 'database' => ':memory:']]);
        $appA = new Application('/var/www/appA', $configA);
        $appA->bootstrapWith([new DatabaseBootstrapper(), new OrmBootstrapper()]);

        $configB = new ConfigRepository(['database' => ['driver' => 'sqlite', 'database' => ':memory:']]);
        $appB = new Application('/var/www/appB', $configB);
        $appB->bootstrapWith([new DatabaseBootstrapper(), new OrmBootstrapper()]);

        $managerA = $appA->container()->get(OrmManager::class);
        $managerB = $appB->container()->get(OrmManager::class);

        $this->assertNotSame($managerA, $managerB);
    }

    // 6. OrmManager receives the same ConnectionInterface instance registered by DatabaseBootstrapper.
    #[Test]
    public function orm_manager_receives_the_container_connection_instance(): void
    {
        $config = new ConfigRepository(['database' => ['driver' => 'sqlite', 'database' => ':memory:']]);
        $app = new Application('/var/www/myapp', $config);
        
        $app->bootstrapWith([
            new DatabaseBootstrapper(),
            new OrmBootstrapper(),
        ]);

        $connection = $app->container()->get(ConnectionInterface::class);
        $manager = $app->container()->get(OrmManager::class);

        // We use reflection to verify it got the exact connection instance
        $reflection = new ReflectionClass($manager);
        $property = $reflection->getProperty('connection');
        
        $managerConnection = $property->getValue($manager);

        $this->assertSame($connection, $managerConnection);
    }

    // 7. Missing ConnectionInterface causes native Container failure.
    #[Test]
    public function missing_database_binding_throws_container_exception(): void
    {
        $app = new Application('/var/www/myapp');
        $app->bootstrapWith([new OrmBootstrapper()]); // Did not bootstrap database

        $this->expectException(NotFoundException::class);
        
        $app->container()->get(OrmManager::class);
    }

    // 8. Repeated OrmBootstrapper registration follows existing singleton replacement semantics.
    #[Test]
    public function repeated_bootstrap_invalidates_cached_instance(): void
    {
        $config = new ConfigRepository(['database' => ['driver' => 'sqlite', 'database' => ':memory:']]);
        $app = new Application('/var/www/myapp', $config);
        
        $app->bootstrapWith([
            new DatabaseBootstrapper(),
            new OrmBootstrapper(),
        ]);
        
        $first = $app->container()->get(OrmManager::class);
        
        // Register it again
        $app->bootstrapWith([new OrmBootstrapper()]);
        
        $second = $app->container()->get(OrmManager::class);

        $this->assertNotSame($first, $second); // Instance was refreshed
    }

    // 9. OrmBootstrapper does not register unrelated services.
    #[Test]
    public function it_does_not_register_unrelated_services(): void
    {
        $app = new Application('/var/www/myapp');
        
        // Base container has config + self-registered application.
        // We ensure bootstrapper only adds OrmManager.
        $app->bootstrapWith([new OrmBootstrapper()]);

        $this->assertTrue($app->container()->has(OrmManager::class));
        
        // Assert it does not register Model classes, etc.
        $this->assertFalse($app->container()->has('Model'));
        $this->assertFalse($app->container()->has('ModelHydrator'));
        $this->assertFalse($app->container()->has('ModelQueryBuilder'));
    }
}
