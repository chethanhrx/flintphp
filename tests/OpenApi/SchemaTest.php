<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\OpenApi;

use FlintPHP\Framework\OpenApi\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Schema::class)]
final class SchemaTest extends TestCase
{
    #[Test]
    public function it_creates_schema_and_preserves_explicit_values(): void
    {
        $schema = new Schema(
            type: 'string',
            example: null // Explicit null
        );
        
        $array = $schema->toArray();
        $this->assertArrayHasKey('example', $array);
        $this->assertNull($array['example']);
        $this->assertSame('string', $array['type']);
    }

    #[Test]
    public function it_preserves_falsy_examples(): void
    {
        $schemaFalse = new Schema(example: false);
        $this->assertSame(false, $schemaFalse->toArray()['example']);

        $schemaZero = new Schema(example: 0);
        $this->assertSame(0, $schemaZero->toArray()['example']);

        $schemaEmpty = new Schema(example: '');
        $this->assertSame('', $schemaEmpty->toArray()['example']);
        
        $schemaEmptyArray = new Schema(example: []);
        $this->assertSame([], $schemaEmptyArray->toArray()['example']);
    }

    #[Test]
    public function it_outputs_empty_object_for_empty_properties(): void
    {
        $schema = new Schema(properties: []);
        $this->assertInstanceOf(\stdClass::class, $schema->toArray()['properties']);
    }
}
