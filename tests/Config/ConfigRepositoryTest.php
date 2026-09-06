<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Config;

use FlintPHP\Framework\Config\ConfigRepository;
use FlintPHP\Framework\Config\Contract\ConfigRepositoryInterface;
use FlintPHP\Framework\Config\Exception\ConfigurationException;
use FlintPHP\Framework\Container\Container;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigRepository::class)]
final class ConfigRepositoryTest extends TestCase
{
    // ── Construction ──

    #[Test]
    public function it_can_be_constructed_with_empty_array(): void
    {
        $repo = new ConfigRepository([]);
        $this->assertInstanceOf(ConfigRepositoryInterface::class, $repo);
        $this->assertSame([], $repo->all());
    }

    #[Test]
    public function it_can_be_constructed_with_simple_configuration(): void
    {
        $repo = new ConfigRepository(['foo' => 'bar']);
        $this->assertSame('bar', $repo->get('foo'));
    }

    #[Test]
    public function it_can_be_constructed_with_nested_configuration(): void
    {
        $repo = new ConfigRepository(['app' => ['name' => 'FlintPHP']]);
        $this->assertSame('FlintPHP', $repo->get('app.name'));
    }

    #[Test]
    public function it_does_not_execute_callable_values(): void
    {
        $callable = fn() => 'executed';
        $repo = new ConfigRepository(['callback' => $callable]);

        $this->assertSame($callable, $repo->get('callback'));
    }

    #[Test]
    public function it_returns_objects_as_opaque_values(): void
    {
        $obj = new \stdClass();
        $repo = new ConfigRepository(['obj' => $obj]);

        $this->assertSame($obj, $repo->get('obj'));
    }

    // ── get() ──

    #[Test]
    public function get_returns_root_key(): void
    {
        $repo = new ConfigRepository(['foo' => 'bar']);
        $this->assertSame('bar', $repo->get('foo'));
    }

    #[Test]
    public function get_returns_nested_key(): void
    {
        $repo = new ConfigRepository(['foo' => ['bar' => 'baz']]);
        $this->assertSame('baz', $repo->get('foo.bar'));
    }

    #[Test]
    public function get_returns_deeply_nested_key(): void
    {
        $repo = new ConfigRepository([
            'database' => [
                'connections' => [
                    'primary' => [
                        'host' => 'localhost'
                    ]
                ]
            ]
        ]);
        $this->assertSame('localhost', $repo->get('database.connections.primary.host'));
    }

    #[Test]
    public function get_returns_null_for_missing_key(): void
    {
        $repo = new ConfigRepository([]);
        $this->assertNull($repo->get('does.not.exist'));
    }

    #[Test]
    public function get_returns_default_for_missing_key(): void
    {
        $repo = new ConfigRepository([]);
        $this->assertSame(5432, $repo->get('does.not.exist', 5432));
    }

    #[Test]
    public function get_returns_explicit_null_configured_value_over_default(): void
    {
        $repo = new ConfigRepository(['database' => ['password' => null]]);
        $this->assertNull($repo->get('database.password', 'fallback'));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function falseyValueProvider(): array
    {
        return [
            'false' => [false],
            'zero integer' => [0],
            'zero float' => [0.0],
            'empty string' => [''],
            'empty array' => [[]],
            'null' => [null],
        ];
    }

    #[Test]
    #[DataProvider('falseyValueProvider')]
    public function get_preserves_falsey_values_over_default(mixed $falseyValue): void
    {
        $repo = new ConfigRepository(['key' => $falseyValue]);
        $this->assertSame($falseyValue, $repo->get('key', 'default_fallback'));
    }

    #[Test]
    public function get_treats_scalar_traversal_as_missing_path(): void
    {
        $repo = new ConfigRepository(['app' => ['name' => 'FlintPHP']]);
        $this->assertSame('unknown', $repo->get('app.name.version', 'unknown'));
    }

    // ── has() ──

    #[Test]
    public function has_returns_true_for_existing_root_key(): void
    {
        $repo = new ConfigRepository(['foo' => 'bar']);
        $this->assertTrue($repo->has('foo'));
    }

    #[Test]
    public function has_returns_true_for_existing_nested_key(): void
    {
        $repo = new ConfigRepository(['foo' => ['bar' => 'baz']]);
        $this->assertTrue($repo->has('foo.bar'));
    }

    #[Test]
    public function has_returns_true_for_deeply_nested_key(): void
    {
        $repo = new ConfigRepository([
            'database' => [
                'connections' => [
                    'primary' => [
                        'host' => 'localhost'
                    ]
                ]
            ]
        ]);
        $this->assertTrue($repo->has('database.connections.primary.host'));
    }

    #[Test]
    public function has_returns_false_for_missing_key(): void
    {
        $repo = new ConfigRepository([]);
        $this->assertFalse($repo->has('missing'));
        $this->assertFalse($repo->has('missing.nested'));
    }

    #[Test]
    #[DataProvider('falseyValueProvider')]
    public function has_returns_true_even_when_value_is_falsey(mixed $falseyValue): void
    {
        $repo = new ConfigRepository(['key' => $falseyValue]);
        $this->assertTrue($repo->has('key'));
    }

    #[Test]
    public function has_returns_false_for_scalar_traversal(): void
    {
        $repo = new ConfigRepository(['app' => ['name' => 'FlintPHP']]);
        $this->assertFalse($repo->has('app.name.version'));
    }

    // ── all() ──

    #[Test]
    public function all_returns_complete_configuration(): void
    {
        $config = ['app' => ['name' => 'FlintPHP']];
        $repo = new ConfigRepository($config);
        $this->assertSame($config, $repo->all());
    }

    #[Test]
    public function modifying_returned_all_array_does_not_mutate_repository(): void
    {
        $repo = new ConfigRepository([
            'app' => [
                'name' => 'FlintPHP',
            ],
            'database' => 'postgres',
        ]);

        $all = $repo->all();

        // Mutate the returned array
        $all['app']['name'] = 'HACKED';
        $all['new'] = 'value';
        unset($all['database']);

        // Repository should be unchanged
        $this->assertSame('FlintPHP', $repo->get('app.name'));
        $this->assertNull($repo->get('new'));
        $this->assertTrue($repo->has('database'));
    }

    // ── Key validation ──

    /**
     * @return array<string, array{string}>
     */
    public static function validKeyProvider(): array
    {
        return [
            'single segment' => ['app'],
            'multiple segments' => ['app.name'],
            'underscores' => ['app_debug'],
            'hyphens' => ['database.primary-host'],
            'digits' => ['a1'],
            'dots and letters' => ['a.b.c'],
            '128 char limit' => [str_repeat('a', 128)],
        ];
    }


    #[Test]
    #[DataProvider('validKeyProvider')]
    public function it_does_not_throw_for_valid_keys_on_get(string $validKey): void
    {
        $repo = new ConfigRepository([]);
        $this->assertNull($repo->get($validKey)); // Just ensuring no exception is thrown
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidKeyProvider(): array
    {
        return [
            'empty' => [''],
            'leading dot' => ['.app'],
            'trailing dot' => ['app.'],
            'double dot' => ['app..name'],
            'just dot' => ['.'],
            'slash' => ['app/name'],
            'colon' => ['app:name'],
            'spaces' => ['app name'],
            'tabs' => ["app\tname"],
            'newline' => ["app\nname"],
            'carriage return' => ["app\rname"],
            'control char' => ["app\x00name"],
            'unicode' => ['app🔥name'],
            'over 128 chars' => [str_repeat('a', 129)],
        ];
    }

    #[Test]
    #[DataProvider('invalidKeyProvider')]
    public function it_rejects_invalid_keys_in_get(string $invalidKey): void
    {
        $repo = new ConfigRepository([]);
        $this->expectException(ConfigurationException::class);
        $repo->get($invalidKey);
    }

    #[Test]
    #[DataProvider('invalidKeyProvider')]
    public function it_rejects_invalid_keys_in_has(string $invalidKey): void
    {
        $repo = new ConfigRepository([]);
        $this->expectException(ConfigurationException::class);
        $repo->has($invalidKey);
    }

    // ── DI ──

    #[Test]
    public function it_supports_constructor_injection_after_explicit_registration(): void
    {
        $container = new Container();
        $repo = new ConfigRepository(['app' => ['name' => 'FlintPHP']]);

        // 1. Explicitly register
        $container->singleton(ConfigRepositoryInterface::class, $repo);

        // 2. Resolve a consumer that depends on it
        $consumer = $container->get(ConfigConsumer::class);

        // 3. Assert exact instance and behavior
        $this->assertInstanceOf(ConfigConsumer::class, $consumer);
        $this->assertSame($repo, $consumer->config);
        $this->assertSame('FlintPHP', $consumer->config->get('app.name'));
    }
}

final class ConfigConsumer
{
    public function __construct(
        public readonly ConfigRepositoryInterface $config
    ) {
    }
}
