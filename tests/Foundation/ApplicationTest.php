<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Foundation;

use FlintPHP\Framework\Foundation\Application;
use FlintPHP\Framework\Foundation\FlintPHP;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Application::class)]
final class ApplicationTest extends TestCase
{
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
    public function it_returns_the_framework_version(): void
    {
        $app = new Application('/var/www/myapp');

        $this->assertSame(FlintPHP::VERSION, $app->version());
    }

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
    public function it_can_be_booted_multiple_times_safely(): void
    {
        $app = new Application('/var/www/myapp');
        $app->boot();
        $app->boot();

        $this->assertTrue($app->isBooted());
    }

    #[Test]
    public function it_preserves_root_path(): void
    {
        $app = new Application('/');

        $this->assertSame('/', $app->basePath());
    }
}
