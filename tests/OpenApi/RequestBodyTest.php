<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\OpenApi;

use FlintPHP\Framework\OpenApi\RequestBody;
use FlintPHP\Framework\OpenApi\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequestBody::class)]
final class RequestBodyTest extends TestCase
{
    #[Test]
    public function it_creates_request_body(): void
    {
        $body = new RequestBody(
            content: ['application/json' => new Schema('object')],
            required: true
        );
        
        $array = $body->toArray();
        $this->assertTrue($array['required']);
        $this->assertSame('object', $array['content']['application/json']['schema']['type']);
    }

    #[Test]
    public function it_outputs_empty_object_for_empty_content(): void
    {
        $body = new RequestBody([]);
        $this->assertInstanceOf(\stdClass::class, $body->toArray()['content']);
    }
}
