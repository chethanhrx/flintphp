<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\OpenApi;

use FlintPHP\Framework\OpenApi\Reference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Reference::class)]
final class ReferenceTest extends TestCase
{
    #[Test]
    public function it_creates_reference(): void
    {
        $ref = new Reference('#/components/schemas/User');
        $this->assertSame(['$ref' => '#/components/schemas/User'], $ref->toArray());
    }
}
