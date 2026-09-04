<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Routing;

use FlintPHP\Framework\Container\Container;
use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;
use FlintPHP\Framework\Routing\Exception\InvalidHandlerException;
use FlintPHP\Framework\Routing\Exception\UnresolvableParameterException;
use FlintPHP\Framework\Routing\HandlerInvoker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(HandlerInvoker::class)]
final class HandlerInvokerTest extends TestCase
{
    private Container $container;
    private HandlerInvoker $invoker;
    private Request $request;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->invoker = new HandlerInvoker($this->container);
        $this->request = new Request('GET', '/test');
    }

    // ---------------------------------------------------------------
    // Closures and Legacy BC
    // ---------------------------------------------------------------

    #[Test]
    public function it_invokes_closures_with_request_and_params_array_bc(): void
    {
        $handler = function (Request $request, array $params): Response {
            return new Response('Params: ' . implode(',', $params));
        };

        $response = $this->invoker->invoke($handler, $this->request, ['a' => '1', 'b' => '2']);

        $this->assertSame('Params: 1,2', $response->body());
    }

    // ---------------------------------------------------------------
    // Controllers and Auto-wiring
    // ---------------------------------------------------------------

    #[Test]
    public function it_resolves_and_invokes_array_controller(): void
    {
        $handler = [TestController::class, 'show'];

        $response = $this->invoker->invoke($handler, $this->request, ['id' => '42']);

        $this->assertSame('User 42 (Service: yes)', $response->body());
    }

    #[Test]
    public function it_resolves_and_invokes_invokable_controller(): void
    {
        $handler = InvokableController::class;

        $response = $this->invoker->invoke($handler, $this->request, []);

        $this->assertSame('Invoked', $response->body());
    }

    #[Test]
    public function it_injects_request_object_anywhere_in_signature(): void
    {
        $handler = function (string $id, Request $req, string $other): Response {
            return new Response($req->method() . ' ' . $id . ' ' . $other);
        };

        $response = $this->invoker->invoke($handler, $this->request, ['id' => '10', 'other' => 'abc']);

        $this->assertSame('GET 10 abc', $response->body());
    }

    #[Test]
    public function it_casts_route_parameters_to_scalar_types(): void
    {
        $handler = function (int $id, float $score, bool $active): Response {
            $types = get_debug_type($id) . '|' . get_debug_type($score) . '|' . get_debug_type($active);
            return new Response($types);
        };

        $response = $this->invoker->invoke($handler, $this->request, ['id' => '42', 'score' => '9.5', 'active' => 'true']);

        $this->assertSame('int|float|bool', $response->body());
    }

    #[Test]
    public function it_uses_default_values_if_parameter_is_missing(): void
    {
        $handler = function (string $name = 'guest'): Response {
            return new Response($name);
        };

        $response = $this->invoker->invoke($handler, $this->request, []);

        $this->assertSame('guest', $response->body());
    }

    #[Test]
    public function it_passes_null_to_nullable_parameters(): void
    {
        $handler = function (?string $name): Response {
            return new Response($name ?? 'null');
        };

        $response = $this->invoker->invoke($handler, $this->request, []);

        $this->assertSame('null', $response->body());
    }

    // ---------------------------------------------------------------
    // Exceptions
    // ---------------------------------------------------------------

    #[Test]
    public function it_throws_if_controller_method_is_not_callable(): void
    {
        $handler = [TestController::class, 'missingMethod'];

        $this->expectException(InvalidHandlerException::class);
        $this->expectExceptionMessage('is not callable');

        $this->invoker->invoke($handler, $this->request, []);
    }

    #[Test]
    public function it_throws_if_handler_does_not_return_response(): void
    {
        $handler = function () {
            return 'not a response object';
        };

        $this->expectException(InvalidHandlerException::class);
        $this->expectExceptionMessage('Handler must return an instance of');

        $this->invoker->invoke($handler, $this->request, []);
    }

    #[Test]
    public function it_throws_if_parameter_cannot_be_resolved(): void
    {
        $handler = function (string $unknownName): Response {
            return new Response();
        };

        $this->expectException(UnresolvableParameterException::class);
        $this->expectExceptionMessage('Cannot resolve parameter $unknownName');

        $this->invoker->invoke($handler, $this->request, []);
    }

    // ---------------------------------------------------------------
    // Strict Casting and Type Safety Audit Tests
    // ---------------------------------------------------------------

    #[Test]
    public function it_strictly_casts_valid_integers_and_rejects_invalid_ones(): void
    {
        $handler = function (int $id): Response {
            return new Response((string) $id);
        };

        // Valid integers
        $this->assertSame('42', $this->invoker->invoke($handler, $this->request, ['id' => '42'])->body());
        $this->assertSame('0', $this->invoker->invoke($handler, $this->request, ['id' => '0'])->body());
        $this->assertSame('-10', $this->invoker->invoke($handler, $this->request, ['id' => '-10'])->body());

        // Invalid integer throws
        $this->expectException(UnresolvableParameterException::class);
        $this->expectExceptionMessage('must be an integer, got "abc"');
        $this->invoker->invoke($handler, $this->request, ['id' => 'abc']);
    }

    #[Test]
    public function it_rejects_mixed_alphanumeric_integers(): void
    {
        $handler = fn(int $id) => new Response();

        $this->expectException(UnresolvableParameterException::class);
        $this->expectExceptionMessage('must be an integer, got "12abc"');
        $this->invoker->invoke($handler, $this->request, ['id' => '12abc']);
    }

    #[Test]
    public function it_rejects_empty_string_for_integer(): void
    {
        $handler = fn(int $id) => new Response();

        $this->expectException(UnresolvableParameterException::class);
        $this->invoker->invoke($handler, $this->request, ['id' => '']);
    }

    #[Test]
    public function it_strictly_casts_booleans(): void
    {
        $handler = fn(bool $flag): Response => new Response($flag ? 'Y' : 'N');

        $this->assertSame('Y', $this->invoker->invoke($handler, $this->request, ['flag' => 'true'])->body());
        $this->assertSame('Y', $this->invoker->invoke($handler, $this->request, ['flag' => '1'])->body());
        $this->assertSame('N', $this->invoker->invoke($handler, $this->request, ['flag' => 'false'])->body());
        $this->assertSame('N', $this->invoker->invoke($handler, $this->request, ['flag' => '0'])->body());

        $this->expectException(UnresolvableParameterException::class);
        $this->invoker->invoke($handler, $this->request, ['flag' => 'not_a_bool']);
    }

    #[Test]
    public function route_parameter_cannot_override_class_dependency(): void
    {
        // Container has TestService.
        // Route has variable {service} = "hacked"
        // Method expects TestService $service
        // The container should resolve it, NOT the route parameter.
        
        $this->container->set(TestService::class, new TestService());
        
        $handler = function (TestService $service): Response {
            return new Response($service->check());
        };

        $response = $this->invoker->invoke($handler, $this->request, ['service' => 'hacked']);
        $this->assertSame('yes', $response->body());
    }

    #[Test]
    public function unresolvable_class_dependency_does_not_fallback_to_route_parameter(): void
    {
        // UnboundService is NOT in container and not auto-wireable (no constructor/etc) 
        // OR let's just make it an interface
        $handler = function (UnboundInterface $dependency): Response {
            return new Response();
        };

        $this->expectException(UnresolvableParameterException::class);
        $this->expectExceptionMessage('Cannot resolve class dependency');
        
        $this->invoker->invoke($handler, $this->request, ['dependency' => 'some_string']);
    }
}

interface UnboundInterface {}

// ---------------------------------------------------------------
// Stubs
// ---------------------------------------------------------------

class TestService
{
    public function check(): string
    {
        return 'yes';
    }
}

class TestController
{
    public function __construct(private TestService $service) {}

    public function show(string $id): Response
    {
        return new Response('User ' . $id . ' (Service: ' . $this->service->check() . ')');
    }
}

class InvokableController
{
    public function __invoke(): Response
    {
        return new Response('Invoked');
    }
}
