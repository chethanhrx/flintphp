<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\OpenApi;

use FlintPHP\Framework\OpenApi\Components;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Components::class)]
final class ComponentsTest extends TestCase
{
    #[Test]
    public function it_outputs_empty_objects_for_empty_collections(): void
    {
        $comp = new Components(
            schemas: [],
            responses: [],
            parameters: [],
            requestBodies: []
        );
        
        $array = $comp->toArray();
        $this->assertInstanceOf(\stdClass::class, $array['schemas']);
        $this->assertInstanceOf(\stdClass::class, $array['responses']);
        $this->assertInstanceOf(\stdClass::class, $array['parameters']);
        $this->assertInstanceOf(\stdClass::class, $array['requestBodies']);
    }
}
