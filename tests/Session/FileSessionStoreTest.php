<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Session;

use FlintPHP\Framework\Session\Store\FileSessionStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(FileSessionStore::class)]
final class FileSessionStoreTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/flintphp-session-test-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->directory)) {
            return;
        }

        foreach (scandir($this->directory) ?: [] as $file) {
            if ($file !== '.' && $file !== '..') {
                @unlink($this->directory . DIRECTORY_SEPARATOR . $file);
            }
        }

        @rmdir($this->directory);
    }

    #[Test]
    public function session_survives_a_new_store_instance(): void
    {
        $id = str_repeat('a', 64);
        $first = new FileSessionStore($this->directory);
        $first->write($id, ['user_id' => 42, 'preferences' => ['theme' => 'dark']], time() + 60);

        $second = new FileSessionStore($this->directory);

        self::assertSame(
            ['user_id' => 42, 'preferences' => ['theme' => 'dark']],
            $second->read($id),
        );
    }

    #[Test]
    public function expired_sessions_are_not_returned(): void
    {
        $store = new FileSessionStore($this->directory);
        $id = str_repeat('b', 64);
        $store->write($id, ['role' => 'member'], time() - 1);

        self::assertNull($store->read($id));
    }

    #[Test]
    public function deleting_a_session_removes_persisted_data(): void
    {
        $store = new FileSessionStore($this->directory);
        $id = str_repeat('c', 64);
        $store->write($id, ['active' => true], time() + 60);
        $store->delete($id);

        self::assertNull($store->read($id));
    }

    #[Test]
    public function malformed_session_ids_are_rejected(): void
    {
        $store = new FileSessionStore($this->directory);

        $this->expectException(RuntimeException::class);
        $store->read('../outside');
    }

    #[Test]
    public function corrupted_session_files_are_treated_as_invalid(): void
    {
        $store = new FileSessionStore($this->directory);
        $id = str_repeat('d', 64);
        file_put_contents($this->directory . DIRECTORY_SEPARATOR . $id . '.json', '{broken');

        self::assertNull($store->read($id));
    }

    #[Test]
    public function storage_directory_and_session_files_use_restrictive_permissions(): void
    {
        $store = new FileSessionStore($this->directory);
        $id = str_repeat('e', 64);
        $store->write($id, ['ok' => true], time() + 60);

        clearstatcache(true, $this->directory);
        clearstatcache(true, $this->directory . DIRECTORY_SEPARATOR . $id . '.json');

        self::assertSame(0700, fileperms($this->directory) & 0777);
        self::assertSame(0600, fileperms($this->directory . DIRECTORY_SEPARATOR . $id . '.json') & 0777);
    }
}
