<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\OpenApi;

use FlintPHP\Framework\OpenApi\Parameter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Parameter::class)]
final class ParameterTest extends TestCase
{
    #[Test]
    public function it_creates_parameter(): void
    {
        $param = new Parameter('id', 'path', 'User ID', true);
        
        $this->assertSame([
            'name' => 'id',
            'in' => 'path',
            'description' => 'User ID',
            'required' => true,
        ], $param->toArray());
    }
}
