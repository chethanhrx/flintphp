<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Cache;

use FlintPHP\Framework\Cache\ArrayCache;
use FlintPHP\Framework\Cache\Exception\InvalidArgumentException;
use FlintPHP\Framework\Cache\Exception\InvalidCacheValueException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArrayCache::class)]
final class ArrayCacheTest extends TestCase
{
    private ArrayCache $cache;

    protected function setUp(): void
    {
        $this->cache = new ArrayCache();
    }

    #[Test]
    public function it_can_set_and_get_values(): void
    {
        $this->assertTrue($this->cache->set('foo', 'bar'));
        $this->assertTrue($this->cache->has('foo'));
        $this->assertSame('bar', $this->cache->get('foo'));
    }

    #[Test]
    public function it_returns_default_when_key_does_not_exist(): void
    {
        $this->assertFalse($this->cache->has('missing'));
        $this->assertNull($this->cache->get('missing'));
        $this->assertSame('default_val', $this->cache->get('missing', 'default_val'));
    }

    #[Test]
    public function it_can_delete_keys(): void
    {
        $this->cache->set('foo', 'bar');
        $this->assertTrue($this->cache->delete('foo'));
        $this->assertFalse($this->cache->has('foo'));
    }

    #[Test]
    public function it_can_clear_all_keys(): void
    {
        $this->cache->set('foo', 'bar');
        $this->cache->set('baz', 'qux');
        $this->assertTrue($this->cache->clear());
        $this->assertFalse($this->cache->has('foo'));
        $this->assertFalse($this->cache->has('baz'));
    }

    #[Test]
    public function it_handles_expired_ttl(): void
    {
        $this->cache->set('foo', 'bar', -1);
        $this->assertFalse($this->cache->has('foo'));
        $this->assertNull($this->cache->get('foo'));
    }
    
    #[Test]
    public function it_handles_zero_ttl(): void
    {
        $this->cache->set('foo', 'bar', 0);
        $this->assertFalse($this->cache->has('foo'));
    }

    #[Test]
    public function it_rejects_invalid_keys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache->get('invalid key!');
    }
    
    #[Test]
    public function it_rejects_empty_keys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache->get('');
    }

    #[Test]
    public function it_rejects_too_long_keys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache->get(str_repeat('a', 65));
    }

    #[Test]
    public function it_fails_gracefully_when_storing_unserializable_data(): void
    {
        $this->expectException(InvalidCacheValueException::class);
        $resource = fopen('php://memory', 'r');
        
        try {
            $this->cache->set('res', $resource);
        } finally {
            fclose($resource);
        }
    }

    #[Test]
    public function it_stores_and_returns_objects_as_associative_arrays(): void
    {
        $object = new \stdClass();
        $object->name = 'Flint';
        $object->version = 13;

        $this->cache->set('framework', $object);
        
        $result = $this->cache->get('framework');
        $this->assertIsArray($result);
        $this->assertSame(['name' => 'Flint', 'version' => 13], $result);
    }
}
