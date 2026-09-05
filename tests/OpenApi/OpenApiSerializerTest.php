<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\OpenApi;

use FlintPHP\Framework\OpenApi\Exception\SerializationException;
use FlintPHP\Framework\OpenApi\Info;
use FlintPHP\Framework\OpenApi\OpenApiDocument;
use FlintPHP\Framework\OpenApi\OpenApiSerializer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OpenApiSerializer::class)]
final class OpenApiSerializerTest extends TestCase
{
    #[Test]
    public function it_serializes_to_json(): void
    {
        $doc = new OpenApiDocument(new Info('My API', 'v1'));
        
        $serializer = new OpenApiSerializer();
        $json = $serializer->toJson($doc);

        $expected = '{"openapi":"3.1.0","info":{"title":"My API","version":"v1"},"paths":{}}';
        $this->assertSame($expected, $json);
    }

    #[Test]
    public function it_throws_on_serialization_failure(): void
    {
        // Create an Info object with invalid UTF-8 to force json_encode to fail
        $invalidUtf8 = "\xB1\x31";
        $doc = new OpenApiDocument(new Info($invalidUtf8, '1.0'));
        
        $serializer = new OpenApiSerializer();

        $this->expectException(SerializationException::class);
        $this->expectExceptionMessage('Failed to serialize OpenAPI document to JSON: Malformed UTF-8 characters, possibly incorrectly encoded');

        $serializer->toJson($doc);
    }
}
