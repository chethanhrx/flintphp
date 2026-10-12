<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Config;

use FlintPHP\Framework\Config\Exception\ConfigurationException;
use FlintPHP\Framework\Config\PhpFileLoader;
use PHPUnit\Framework\TestCase;

final class PhpFileLoaderTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/flint-config-' . uniqid('', true);
        mkdir($this->dir);
        file_put_contents($this->dir . '/app.php', "<?php\nreturn ['name' => 'Flint'];\n");
        file_put_contents($this->dir . '/database.php', "<?php\nreturn ['driver' => 'sqlite'];\n");
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            unlink($f);
        }
        rmdir($this->dir);
    }

    public function testLoadDirectory(): void
    {
        $config = PhpFileLoader::loadDirectory($this->dir);

        $this->assertSame('Flint', $config['app']['name']);
        $this->assertSame('sqlite', $config['database']['driver']);
    }

    public function testMissingDirectoryThrows(): void
    {
        $this->expectException(ConfigurationException::class);
        PhpFileLoader::loadDirectory($this->dir . '/missing');
    }
}
