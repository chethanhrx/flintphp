<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Cache;

use FlintPHP\Framework\Cache\DatabaseCache;
use FlintPHP\Framework\Database\ConnectionFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DatabaseCacheTest extends TestCase
{
    private DatabaseCache $cache;

    protected function setUp(): void
    {
        $db = ConnectionFactory::make(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->cache = new DatabaseCache($db);
    }

    #[Test]
    public function set_get_delete_and_has(): void
    {
        self::assertTrue($this->cache->set('user.1', ['name' => 'Ada'], 60));
        self::assertTrue($this->cache->has('user.1'));
        self::assertSame(['name' => 'Ada'], $this->cache->get('user.1'));

        self::assertTrue($this->cache->delete('user.1'));
        self::assertFalse($this->cache->has('user.1'));
        self::assertSame('missing', $this->cache->get('user.1', 'missing'));
    }

    #[Test]
    public function increment_is_atomic_within_connection(): void
    {
        self::assertSame(1, $this->cache->increment('rl.demo', 1, 60));
        self::assertSame(2, $this->cache->increment('rl.demo', 1, 60));
        self::assertSame(5, $this->cache->increment('rl.demo', 3));
    }

    #[Test]
    public function clear_wipes_all_keys(): void
    {
        $this->cache->set('a', 1);
        $this->cache->set('b', 2);
        self::assertTrue($this->cache->clear());
        self::assertFalse($this->cache->has('a'));
        self::assertFalse($this->cache->has('b'));
    }
}
