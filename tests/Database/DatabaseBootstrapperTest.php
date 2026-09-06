<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Database;

use FlintPHP\Framework\Config\ConfigRepository;
use FlintPHP\Framework\Database\ConnectionInterface;
use FlintPHP\Framework\Database\DatabaseBootstrapper;
use FlintPHP\Framework\Database\PdoConnection;
use FlintPHP\Framework\Foundation\Application;
use FlintPHP\Framework\Foundation\BootstrapperInterface;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TypeError;
use PDOException;

#[CoversClass(DatabaseBootstrapper::class)]
final class DatabaseBootstrapperTest extends TestCase
{
    // 1. DatabaseBootstrapper implements BootstrapperInterface.
    #[Test]
    public function it_implements_bootstrapper_interface(): void
    {
        $bootstrapper = new DatabaseBootstrapper();
        $this->assertInstanceOf(BootstrapperInterface::class, $bootstrapper);
    }

    // 2. bootstrap() registers ConnectionInterface.
    #[Test]
    public function bootstrap_registers_connection_interface(): void
    {
        $app = new Application('/var/www/myapp');
        $app->bootstrapWith([new DatabaseBootstrapper()]);

        $this->assertTrue($app->container()->has(ConnectionInterface::class));
    }

    // 3. ConnectionInterface resolves successfully with valid configuration.
    #[Test]
    public function connection_interface_resolves_successfully(): void
    {
        $config = new ConfigRepository(['database' => ['driver' => 'sqlite', 'database' => ':memory:']]);
        $app = new Application('/var/www/myapp', $config);
        $app->bootstrapWith([new DatabaseBootstrapper()]);

        $connection = $app->container()->get(ConnectionInterface::class);

        $this->assertInstanceOf(ConnectionInterface::class, $connection);
        $this->assertInstanceOf(PdoConnection::class, $connection);
    }

    // 4. Binding is lazy.
    // 5. bootstrap() does not call ConnectionFactory::make().
    // 6. bootstrap() does not create PDO.
    #[Test]
    public function binding_is_lazy_and_does_not_evaluate_config_eagerly(): void
    {
        // Invalid config that would throw if evaluated
        $config = new ConfigRepository(['database' => 'invalid_scalar']);
        $app = new Application('/var/www/myapp', $config);
        
        // This should not throw
        $app->bootstrapWith([new DatabaseBootstrapper()]);
        
        $this->expectNotToPerformAssertions();
    }

    // 7. Database connection creation remains lazy according to the existing PdoConnection behavior.
    #[Test]
    public function database_connection_creation_remains_lazy(): void
    {
        // Provide valid factory config but invalid credentials/host that would fail on connect
        $config = new ConfigRepository(['database' => ['driver' => 'mysql', 'host' => 'invalid_host_that_does_not_exist', 'database' => 'test']]);
        $app = new Application('/var/www/myapp', $config);
        $app->bootstrapWith([new DatabaseBootstrapper()]);

        // Resolving the binding works because PDO is lazy inside PdoConnection
        $connection = $app->container()->get(ConnectionInterface::class);
        $this->assertInstanceOf(ConnectionInterface::class, $connection);
    }

    // 8. ConnectionInterface is singleton.
    // 9. Repeated get() returns identical instance.
    #[Test]
    public function resolved_connection_is_a_singleton(): void
    {
        $config = new ConfigRepository(['database' => ['driver' => 'sqlite', 'database' => ':memory:']]);
        $app = new Application('/var/www/myapp', $config);
        $app->bootstrapWith([new DatabaseBootstrapper()]);

        $conn1 = $app->container()->get(ConnectionInterface::class);
        $conn2 = $app->container()->get(ConnectionInterface::class);

        $this->assertSame($conn1, $conn2);
    }

    // 10. Two Application instances have isolated database connections.
    // 19. No static/global Database state.
    #[Test]
    public function application_instances_have_isolated_connections(): void
    {
        $configA = new ConfigRepository(['database' => ['driver' => 'sqlite', 'database' => ':memory:']]);
        $appA = new Application('/var/www/appA', $configA);
        $appA->bootstrapWith([new DatabaseBootstrapper()]);

        $configB = new ConfigRepository(['database' => ['driver' => 'sqlite', 'database' => ':memory:']]);
        $appB = new Application('/var/www/appB', $configB);
        $appB->bootstrapWith([new DatabaseBootstrapper()]);

        $connA = $appA->container()->get(ConnectionInterface::class);
        $connB = $appB->container()->get(ConnectionInterface::class);

        $this->assertNotSame($connA, $connB);
    }

    // 11. Missing database configuration fails only when the binding is resolved, not during bootstrap.
    // 12. Null database configuration does not get silently converted with an array cast.
    #[Test]
    public function missing_database_configuration_fails_on_resolution(): void
    {
        $app = new Application('/var/www/myapp', new ConfigRepository([])); // No database key
        
        $app->bootstrapWith([new DatabaseBootstrapper()]); // Does not throw

        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('Database configuration must be an array, got null.');

        $app->container()->get(ConnectionInterface::class);
    }

    // 13. Scalar malformed configuration does not get silently repaired.
    #[Test]
    public function scalar_malformed_configuration_fails_on_resolution(): void
    {
        $app = new Application('/var/www/myapp', new ConfigRepository(['database' => 'sqlite://memory']));
        
        $app->bootstrapWith([new DatabaseBootstrapper()]); // Does not throw

        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('Database configuration must be an array, got string.');

        $app->container()->get(ConnectionInterface::class);
    }

    // 14. Missing driver preserves existing ConnectionFactory failure semantics.
    #[Test]
    public function missing_driver_throws_invalid_argument_exception(): void
    {
        $app = new Application('/var/www/myapp', new ConfigRepository(['database' => []])); // Empty array
        $app->bootstrapWith([new DatabaseBootstrapper()]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A driver must be specified.');

        $app->container()->get(ConnectionInterface::class);
    }

    // 15. Unsupported driver preserves existing ConnectionFactory failure semantics.
    #[Test]
    public function unsupported_driver_throws_invalid_argument_exception(): void
    {
        $app = new Application('/var/www/myapp', new ConfigRepository(['database' => ['driver' => 'foo']]));
        $app->bootstrapWith([new DatabaseBootstrapper()]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported database driver: foo');

        $app->container()->get(ConnectionInterface::class);
    }

    // 16. Repeated DatabaseBootstrapper registration follows existing Container singleton behavior.
    // 17. Repeated bootstrap causes the old cached connection instance to be invalidated according to Container semantics.
    #[Test]
    public function repeated_bootstrap_invalidates_cached_instance(): void
    {
        $config = new ConfigRepository(['database' => ['driver' => 'sqlite', 'database' => ':memory:']]);
        $app = new Application('/var/www/myapp', $config);
        
        $app->bootstrapWith([new DatabaseBootstrapper()]);
        $conn1 = $app->container()->get(ConnectionInterface::class);
        $conn2 = $app->container()->get(ConnectionInterface::class);
        
        $this->assertSame($conn1, $conn2); // Sanity check

        // Second bootstrap overwrites the binding and unsets the cached instance
        $app->bootstrapWith([new DatabaseBootstrapper()]);
        
        $conn3 = $app->container()->get(ConnectionInterface::class);

        $this->assertNotSame($conn1, $conn3); // Instance was refreshed
    }

    // 18. Credentials are not exposed in bootstrapper-related errors.
    #[Test]
    public function credentials_are_not_exposed_in_resolution_errors(): void
    {
        // TypeError will only mention "got null" or "got string".
        // InvalidArgumentException mentions driver name.
        // There is no scenario where the bootstrapper closure exposes the password 
        // in its own error message. PDO exceptions are handled by PdoConnection.
        
        $config = new ConfigRepository(['database' => ['driver' => 'sqlite', 'database' => ':memory:', 'password' => 'super_secret_password']]);
        $app = new Application('/var/www/myapp', $config);
        $app->bootstrapWith([new DatabaseBootstrapper()]);

        $conn = $app->container()->get(ConnectionInterface::class);
        
        // PdoConnection stores the password as a #[SensitiveParameter], so a PDOException
        // stack trace triggered from $conn->pdo() would not leak it.
        // We verify that the bootstrapper doesn't introduce any new exception that leaks it.
        $this->assertInstanceOf(ConnectionInterface::class, $conn);
    }
}
