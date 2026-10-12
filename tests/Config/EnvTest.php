<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Config;

use FlintPHP\Framework\Config\Env;
use FlintPHP\Framework\Config\Exception\ConfigurationException;
use PHPUnit\Framework\TestCase;

final class EnvTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'flintenv');
    }

    protected function tearDown(): void
    {
        if (is_file($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function testLoadParsesKeysAndIgnoresComments(): void
    {
        file_put_contents($this->tmpFile, <<<'ENV'
# comment
APP_NAME="FlintPHP"
APP_ENV=local

EMPTY_SKIP
APP_DEBUG=true
ENV);

        $env = Env::load($this->tmpFile);

        $this->assertSame('FlintPHP', $env['APP_NAME']);
        $this->assertSame('local', $env['APP_ENV']);
        $this->assertSame('true', $env['APP_DEBUG']);
        $this->assertArrayNotHasKey('EMPTY_SKIP', $env);
    }

    public function testExistingKeysAreNotOverwritten(): void
    {
        file_put_contents($this->tmpFile, "APP_ENV=fromfile\n");

        $env = Env::load($this->tmpFile, ['APP_ENV' => 'preset']);

        $this->assertSame('preset', $env['APP_ENV']);
    }

    public function testMissingFileThrows(): void
    {
        $this->expectException(ConfigurationException::class);
        Env::load('/tmp/flint-env-does-not-exist-'.uniqid('', true));
    }

    public function testGetFallsBackToDefault(): void
    {
        $this->assertSame('x', Env::get('FLINT_MISSING_KEY_XYZ', 'x', []));
    }
}
