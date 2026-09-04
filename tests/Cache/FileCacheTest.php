<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Cache;

use FlintPHP\Framework\Cache\FileCache;
use FlintPHP\Framework\Cache\Exception\InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileCache::class)]
final class FileCacheTest extends TestCase
{
    private string $cacheDir;
    private FileCache $cache;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'flint_cache_test_' . uniqid('', true);
        $this->cache = new FileCache($this->cacheDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->cacheDir)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->cacheDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iterator as $file) {
                if ($file->isDir()) {
                    rmdir($file->getRealPath());
                } else {
                    unlink($file->getRealPath());
                }
            }
            rmdir($this->cacheDir);
        }
    }

    #[Test]
    public function it_can_set_and_get_values(): void
    {
        $this->assertTrue($this->cache->set('foo', 'bar'));
        $this->assertTrue($this->cache->has('foo'));
        $this->assertSame('bar', $this->cache->get('foo'));
    }

    #[Test]
    public function it_can_store_arrays(): void
    {
        $data = ['name' => 'Flint', 'version' => 13];
        $this->assertTrue($this->cache->set('framework', $data));
        $this->assertSame($data, $this->cache->get('framework'));
    }

    #[Test]
    public function it_fails_gracefully_when_storing_unserializable_data(): void
    {
        $this->expectException(\FlintPHP\Framework\Cache\Exception\InvalidCacheValueException::class);
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
    public function it_can_clear_only_cache_files(): void
    {
        $this->cache->set('foo', 'bar');
        
        // Create unrelated files
        $randomJson = $this->cacheDir . DIRECTORY_SEPARATOR . 'composer.json';
        file_put_contents($randomJson, '{}');
        
        $randomTxt = $this->cacheDir . DIRECTORY_SEPARATOR . 'random.txt';
        file_put_contents($randomTxt, 'hello');
        
        $subDir = $this->cacheDir . DIRECTORY_SEPARATOR . 'subdir';
        mkdir($subDir);

        $this->assertTrue($this->cache->clear());
        
        $this->assertFalse($this->cache->has('foo'));
        $this->assertFileExists($randomJson);
        $this->assertFileExists($randomTxt);
        $this->assertDirectoryExists($subDir);
    }

    #[Test]
    public function it_handles_expired_ttl(): void
    {
        $this->cache->set('foo', 'bar', -1);
        $this->assertFalse($this->cache->has('foo'));
        $this->assertNull($this->cache->get('foo'));
    }

    #[Test]
    public function it_handles_malformed_json_payloads(): void
    {
        $this->cache->set('foo', 'bar');
        $path = rtrim($this->cacheDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . hash('sha256', 'foo') . '.json';
        
        // Missing 'value' key
        file_put_contents($path, json_encode(['expires_at' => null]));

        $this->assertFalse($this->cache->has('foo'));
        $this->assertNull($this->cache->get('foo'));
        $this->assertFileDoesNotExist($path);
    }

    #[Test]
    public function it_rejects_path_traversal_keys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache->set('../foo', 'bar');
    }
}
