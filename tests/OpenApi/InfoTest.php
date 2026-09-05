<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\OpenApi;

use FlintPHP\Framework\OpenApi\Info;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Info::class)]
final class InfoTest extends TestCase
{
    #[Test]
    public function it_creates_info_without_description(): void
    {
        $info = new Info('My API', 'v1');
        
        $this->assertSame([
            'title' => 'My API',
            'version' => 'v1',
        ], $info->toArray());
    }

    #[Test]
    public function it_creates_info_with_description(): void
    {
        $info = new Info('My API', 'v2', 'Description here');
        
        $this->assertSame([
            'title' => 'My API',
            'version' => 'v2',
            'description' => 'Description here',
        ], $info->toArray());
    }

    #[Test]
    public function it_preserves_empty_string_description(): void
    {
        $info = new Info('My API', 'v3', '');
        
        $this->assertSame([
            'title' => 'My API',
            'version' => 'v3',
            'description' => '',
        ], $info->toArray());
    }
}
