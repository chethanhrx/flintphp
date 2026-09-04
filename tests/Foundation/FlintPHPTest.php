<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Foundation;

use FlintPHP\Framework\Foundation\FlintPHP;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FlintPHP::class)]
final class FlintPHPTest extends TestCase
{
    #[Test]
    public function it_returns_the_framework_name(): void
    {
        $this->assertSame('FlintPHP', FlintPHP::name());
    }

    #[Test]
    public function it_returns_the_framework_version(): void
    {
        $this->assertSame('0.3.0', FlintPHP::version());
    }

    #[Test]
    public function name_constant_matches_name_method(): void
    {
        $this->assertSame(FlintPHP::NAME, FlintPHP::name());
    }

    #[Test]
    public function version_constant_matches_version_method(): void
    {
        $this->assertSame(FlintPHP::VERSION, FlintPHP::version());
    }
}
