<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\OpenApi;

use FlintPHP\Framework\OpenApi\Components;
use FlintPHP\Framework\OpenApi\Exception\InvalidDocumentException;
use FlintPHP\Framework\OpenApi\Info;
use FlintPHP\Framework\OpenApi\OpenApiDocument;
use FlintPHP\Framework\OpenApi\Operation;
use FlintPHP\Framework\OpenApi\Parameter;
use FlintPHP\Framework\OpenApi\PathItem;
use FlintPHP\Framework\OpenApi\Reference;
use FlintPHP\Framework\OpenApi\RequestBody;
use FlintPHP\Framework\OpenApi\Response;
use FlintPHP\Framework\OpenApi\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OpenApiDocument::class)]
#[CoversClass(Info::class)]
#[CoversClass(PathItem::class)]
#[CoversClass(Operation::class)]
#[CoversClass(Parameter::class)]
#[CoversClass(RequestBody::class)]
#[CoversClass(Response::class)]
#[CoversClass(Schema::class)]
#[CoversClass(Reference::class)]
#[CoversClass(Components::class)]
final class OpenApiDocumentTest extends TestCase
{
    #[Test]
    public function it_creates_a_minimal_document(): void
    {
        $doc = new OpenApiDocument(
            info: new Info('Test API', '1.0.0')
        );

        $expected = [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'Test API',
                'version' => '1.0.0',
            ],
            'paths' => new \stdClass(),
        ];

        $this->assertEquals($expected, $doc->toArray());
    }

    #[Test]
    public function it_creates_a_complex_document(): void
    {
        $doc = new OpenApiDocument(
            info: new Info('Petstore', '1.0.0', 'A sample API'),
            paths: [
                '/pets' => new PathItem(
                    get: new Operation(
                        responses: [
                            '200' => new Response('A list of pets', [
                                'application/json' => new Schema(
                                    type: 'array',
                                    items: new Reference('#/components/schemas/Pet')
                                )
                            ])
                        ],
                        operationId: 'listPets',
                        parameters: [
                            new Parameter('limit', 'query', 'How many items to return', false, new Schema('integer', 'int32'))
                        ]
                    ),
                    post: new Operation(
                        responses: [
                            '201' => new Response('Null response')
                        ],
                        requestBody: new RequestBody(
                            content: [
                                'application/json' => new Reference('#/components/schemas/Pet')
                            ],
                            required: true
                        )
                    )
                )
            ],
            components: new Components(
                schemas: [
                    'Pet' => new Schema(
                        type: 'object',
                        properties: [
                            'id' => new Schema('integer', 'int64'),
                            'name' => new Schema('string')
                        ],
                        required: ['id', 'name']
                    )
                ]
            )
        );

        $array = $doc->toArray();

        $this->assertSame('3.1.0', $array['openapi']);
        $this->assertSame('Petstore', $array['info']['title']);
        $this->assertSame('listPets', $array['paths']['/pets']['get']['operationId']);
        $this->assertSame('query', $array['paths']['/pets']['get']['parameters'][0]['in']);
        $this->assertSame('#/components/schemas/Pet', $array['paths']['/pets']['post']['requestBody']['content']['application/json']['schema']['$ref']);
        $this->assertSame('object', $array['components']['schemas']['Pet']['type']);
    }

    #[Test]
    public function it_rejects_invalid_parameter_location(): void
    {
        $this->expectException(InvalidDocumentException::class);
        $this->expectExceptionMessage('Invalid parameter location "invalid"');

        new Parameter('id', 'invalid');
    }

    #[Test]
    public function it_rejects_invalid_component_names(): void
    {
        $this->expectException(InvalidDocumentException::class);
        $this->expectExceptionMessage('Invalid component name "Invalid Name!" in schemas');

        new Components(
            schemas: [
                'Invalid Name!' => new Schema('string')
            ]
        );
    }

    #[Test]
    public function it_rejects_invalid_paths(): void
    {
        $this->expectException(InvalidDocumentException::class);
        $this->expectExceptionMessage('Invalid path "users"');

        new OpenApiDocument(
            info: new Info('API', 'v1'),
            paths: ['users' => new PathItem()]
        );
    }
}
