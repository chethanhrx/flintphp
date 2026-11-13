<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Session;

use FlintPHP\Framework\Database\ConnectionFactory;
use FlintPHP\Framework\Session\Store\DatabaseSessionStore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DatabaseSessionStoreTest extends TestCase
{
    private DatabaseSessionStore $store;

    protected function setUp(): void
    {
        $db = ConnectionFactory::make(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->store = new DatabaseSessionStore($db);
    }

    #[Test]
    public function writes_reads_and_deletes_session_payload(): void
    {
        $id = str_repeat('ab', 32); // 64 hex chars

        $this->store->write($id, ['user_id' => 7, 'role' => 'admin'], time() + 3600);

        $data = $this->store->read($id);
        self::assertNotNull($data);
        self::assertSame(7, $data['user_id']);
        self::assertSame('admin', $data['role']);

        $this->store->delete($id);
        self::assertNull($this->store->read($id));
    }

    #[Test]
    public function expired_sessions_read_as_null(): void
    {
        $id = str_repeat('cd', 32);
        $this->store->write($id, ['x' => 1], time() - 10);

        self::assertNull($this->store->read($id));
    }

    #[Test]
    public function rejects_invalid_session_ids(): void
    {
        $this->expectException(RuntimeException::class);
        $this->store->read('not-a-valid-id');
    }
}
