<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Routing;

use FlintPHP\Framework\Routing\Route;
use FlintPHP\Framework\Routing\RoutingResult;
use FlintPHP\Framework\Routing\RoutingStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RoutingResult::class)]
final class RoutingResultTest extends TestCase
{
    #[Test]
    public function it_creates_found_result(): void
    {
        $route = new Route('GET', '/users/{id}', fn() => null);
        $params = ['id' => '42'];

        $result = RoutingResult::found($route, $params);

        $this->assertSame(RoutingStatus::FOUND, $result->status());
        $this->assertTrue($result->isFound());
        $this->assertFalse($result->isNotFound());
        $this->assertFalse($result->isMethodNotAllowed());

        $this->assertSame($route, $result->route());
        $this->assertSame($route->handler(), $result->handler());
        $this->assertSame($params, $result->parameters());
        $this->assertSame([], $result->allowedMethods());
    }

    #[Test]
    public function it_creates_not_found_result(): void
    {
        $result = RoutingResult::notFound();

        $this->assertSame(RoutingStatus::NOT_FOUND, $result->status());
        $this->assertFalse($result->isFound());
        $this->assertTrue($result->isNotFound());
        $this->assertFalse($result->isMethodNotAllowed());

        $this->assertNull($result->route());
        $this->assertNull($result->handler());
        $this->assertSame([], $result->parameters());
        $this->assertSame([], $result->allowedMethods());
    }

    #[Test]
    public function it_creates_method_not_allowed_result(): void
    {
        $allowed = ['GET', 'HEAD'];
        $result = RoutingResult::methodNotAllowed($allowed);

        $this->assertSame(RoutingStatus::METHOD_NOT_ALLOWED, $result->status());
        $this->assertFalse($result->isFound());
        $this->assertFalse($result->isNotFound());
        $this->assertTrue($result->isMethodNotAllowed());

        $this->assertNull($result->route());
        $this->assertNull($result->handler());
        $this->assertSame([], $result->parameters());
        $this->assertSame($allowed, $result->allowedMethods());
    }
}
