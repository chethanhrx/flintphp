<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Health;

use FlintPHP\Framework\Database\ConnectionFactory;
use FlintPHP\Framework\Health\HealthChecker;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HealthCheckerTest extends TestCase
{
    #[Test]
    public function healthy_database_returns_ok(): void
    {
        $db = ConnectionFactory::make(['driver' => 'sqlite', 'database' => ':memory:']);
        $checker = HealthChecker::withDatabase($db);
        $result = $checker->check();

        $this->assertSame('ok', $result['status']);
        $this->assertSame('ok', $result['checks']['database']);
        $this->assertSame(200, $checker->toResponse()->status());
    }

    #[Test]
    public function failed_check_is_degraded(): void
    {
        $checker = new HealthChecker([
            'always_fail' => static fn (): bool => false,
        ]);

        $result = $checker->check();
        $this->assertSame('degraded', $result['status']);
        $this->assertSame(503, $checker->toResponse()->status());
    }
}
