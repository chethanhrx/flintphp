<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\OpenApi;

use FlintPHP\Framework\OpenApi\Exception\InvalidDocumentException;
use FlintPHP\Framework\OpenApi\Exception\OpenApiException;
use FlintPHP\Framework\OpenApi\Exception\SerializationException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExceptionTest extends TestCase
{
    #[Test]
    public function it_verifies_exception_hierarchy(): void
    {
        $invalidDoc = new InvalidDocumentException();
        $serialization = new SerializationException();

        $this->assertInstanceOf(OpenApiException::class, $invalidDoc);
        $this->assertInstanceOf(OpenApiException::class, $serialization);
        $this->assertInstanceOf(\RuntimeException::class, $invalidDoc);
    }
}
