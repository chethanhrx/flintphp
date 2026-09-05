<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\OpenApi;

use FlintPHP\Framework\OpenApi\Exception\InvalidDocumentException;
use FlintPHP\Framework\OpenApi\Operation;
use FlintPHP\Framework\OpenApi\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Operation::class)]
final class OperationTest extends TestCase
{
    #[Test]
    public function it_creates_operation(): void
    {
        $op = new Operation(
            responses: ['200' => new Response('OK')],
            operationId: 'getUsers',
            tags: ['Users']
        );
        
        $array = $op->toArray();
        $this->assertSame('getUsers', $array['operationId']);
        $this->assertSame(['Users'], $array['tags']);
        $this->assertArrayHasKey('200', $array['responses']);
    }

    #[Test]
    public function it_rejects_invalid_status_codes(): void
    {
        $this->expectException(InvalidDocumentException::class);
        $this->expectExceptionMessage('Invalid HTTP status code "abc"');
        
        new Operation(['abc' => new Response('Invalid')]);
    }

    #[Test]
    public function it_accepts_valid_status_codes(): void
    {
        $op = new Operation([
            '200' => new Response('OK'),
            '2XX' => new Response('Any 200'),
            'default' => new Response('Default'),
            100 => new Response('Continue'),
        ]);

        $array = $op->toArray();
        $this->assertArrayHasKey('200', $array['responses']);
        $this->assertArrayHasKey('2XX', $array['responses']);
        $this->assertArrayHasKey('default', $array['responses']);
        $this->assertArrayHasKey('100', $array['responses']);
    }

    #[Test]
    public function it_outputs_empty_object_for_empty_responses(): void
    {
        $op = new Operation([]);
        $this->assertInstanceOf(\stdClass::class, $op->toArray()['responses']);
    }
}
