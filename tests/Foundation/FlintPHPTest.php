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
        $this->assertSame('FlintPHP', FlintPHP::NAME);
    }

    #[Test]
    public function it_returns_the_framework_version(): void
    {
        $this->assertSame('1.1.0', FlintPHP::version());
        $this->assertSame('1.1.0', FlintPHP::VERSION);
    }
}
