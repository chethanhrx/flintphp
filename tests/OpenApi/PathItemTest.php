<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\OpenApi;

use FlintPHP\Framework\OpenApi\Operation;
use FlintPHP\Framework\OpenApi\PathItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PathItem::class)]
final class PathItemTest extends TestCase
{
    #[Test]
    public function it_creates_empty_path_item(): void
    {
        $pathItem = new PathItem();
        $this->assertSame([], $pathItem->toArray());
    }

    #[Test]
    public function it_creates_path_item_with_multiple_operations(): void
    {
        $get = new Operation(['200' => new \FlintPHP\Framework\OpenApi\Response('OK')]);
        $post = new Operation(['201' => new \FlintPHP\Framework\OpenApi\Response('Created')]);
        
        $pathItem = new PathItem(
            get: $get,
            post: $post,
            summary: 'My Path'
        );
        
        $array = $pathItem->toArray();
        
        $this->assertArrayHasKey('get', $array);
        $this->assertArrayHasKey('post', $array);
        $this->assertSame('My Path', $array['summary']);
        $this->assertArrayNotHasKey('put', $array);
    }
}
