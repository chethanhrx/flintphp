<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Testing;

use FlintPHP\Framework\Container\Container;
use FlintPHP\Framework\Http\Kernel;
use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;
use FlintPHP\Framework\Middleware\MiddlewareStack;
use FlintPHP\Framework\Routing\HandlerInvoker;
use FlintPHP\Framework\Routing\Router;
use FlintPHP\Framework\Testing\TestCase as FlintTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(FlintTestCase::class)]
final class TestCaseTest extends \PHPUnit\Framework\TestCase
{
    private function createTestingClass(Kernel $kernel): FlintTestCase
    {
        return new class($kernel, 'test') extends FlintTestCase {
            public function __construct(private Kernel $kernel, string $name)
            {
                parent::__construct($name);
            }

            protected function createKernel(): Kernel
            {
                return $this->kernel;
            }

            // Expose protected methods for testing
            public function publicGet(string $uri, array $headers = [])
            {
                return $this->get($uri, $headers);
            }

            public function publicPost(string $uri, string $body = '', array $headers = [])
            {
                return $this->post($uri, $body, $headers);
            }

            public function publicPostJson(string $uri, array $data = [], array $headers = [])
            {
                return $this->postJson($uri, $data, $headers);
            }
        };
    }

    private function buildKernel(callable $handler): Kernel
    {
        $router = new Router();
        $router->add('GET', '/api/users', $handler);
        $router->add('POST', '/api/users', $handler);

        $container = new Container();
        $middlewareStack = new MiddlewareStack([]);
        $invoker = new HandlerInvoker($container);

        return new Kernel($router, $middlewareStack, $invoker);
    }

    #[Test]
    public function it_can_make_get_requests(): void
    {
        $kernel = $this->buildKernel(function (Request $request) {
            $this->assertSame('GET', $request->method());
            $this->assertSame('/api/users?active=1', $request->uri());
            $this->assertSame('Bearer token', $request->header('Authorization'));
            $this->assertSame('1', $request->query('active'));
            $this->assertSame(['active' => '1'], $request->query());
            
            return new Response('ok');
        });

        $testCase = $this->createTestingClass($kernel);
        $response = $testCase->publicGet('/api/users?active=1', ['Authorization' => 'Bearer token']);
        
        $response->assertOk();
    }

    #[Test]
    public function it_can_make_post_requests(): void
    {
        $kernel = $this->buildKernel(function (Request $request) {
            $this->assertSame('POST', $request->method());
            $this->assertSame('/api/users', $request->uri());
            $this->assertSame('raw body data', $request->body());
            
            return new Response('created', 201);
        });

        $testCase = $this->createTestingClass($kernel);
        $response = $testCase->publicPost('/api/users', 'raw body data');
        
        $response->assertStatus(201);
    }

    #[Test]
    public function it_can_make_post_json_requests(): void
    {
        $kernel = $this->buildKernel(function (Request $request) {
            $this->assertSame('POST', $request->method());
            $this->assertSame('/api/users', $request->uri());
            $this->assertSame('application/json', $request->header('Content-Type'));
            $this->assertSame('{"name":"Alice"}', $request->body());
            
            return new Response('created', 201);
        });

        $testCase = $this->createTestingClass($kernel);
        $response = $testCase->publicPostJson('/api/users', ['name' => 'Alice']);
        
        $response->assertStatus(201);
    }

    #[Test]
    public function it_isolates_repeated_requests(): void
    {
        $counter = 0;
        $kernel = $this->buildKernel(function (Request $request) use (&$counter) {
            $counter++;
            return new Response((string) $counter);
        });

        $testCase = $this->createTestingClass($kernel);
        
        $response1 = $testCase->publicGet('/api/users');
        $response1->assertBody('1');
        
        $response2 = $testCase->publicGet('/api/users');
        $response2->assertBody('2');

        // Verify the first response wasn't mutated by the second request
        $response1->assertBody('1');
        $this->assertNotSame($response1->baseResponse, $response2->baseResponse);
    }
}
