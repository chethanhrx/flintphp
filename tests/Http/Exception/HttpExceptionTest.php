<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Http\Exception;

use FlintPHP\Framework\Http\Exception\HttpException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(HttpException::class)]
final class HttpExceptionTest extends TestCase
{
    #[Test]
    public function it_can_be_constructed_with_a_valid_status(): void
    {
        $exception = new HttpException(404, 'Not Found');
        
        $this->assertSame(404, $exception->status());
        $this->assertSame('Not Found', $exception->getMessage());
    }

    #[Test]
    public function it_preserves_previous_exception(): void
    {
        $previous = new RuntimeException('Previous');
        $exception = new HttpException(500, 'Server Error', $previous);
        
        $this->assertSame($previous, $exception->getPrevious());
    }

    public static function validStatuses(): array
    {
        return [
            [100],
            [200],
            [404],
            [500],
            [599],
        ];
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('validStatuses')]
    public function it_accepts_valid_statuses(int $status): void
    {
        $exception = new HttpException($status);
        $this->assertSame($status, $exception->status());
        $this->assertSame('', $exception->getMessage());
    }

    public static function invalidStatuses(): array
    {
        return [
            [99],
            [600],
            [-1],
            [0],
        ];
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidStatuses')]
    public function it_rejects_invalid_statuses(int $status): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Invalid HTTP status code: %d', $status));

        new HttpException($status);
    }
}
