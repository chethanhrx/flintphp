<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\OpenApi;

use FlintPHP\Framework\OpenApi\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Response::class)]
final class ResponseTest extends TestCase
{
    #[Test]
    public function it_creates_response(): void
    {
        $res = new Response('OK', []);
        $array = $res->toArray();
        $this->assertSame('OK', $array['description']);
        $this->assertInstanceOf(\stdClass::class, $array['content']);
    }
}
