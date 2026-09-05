<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Testing;

use FlintPHP\Framework\Http\HeaderBag;
use FlintPHP\Framework\Http\Kernel;
use FlintPHP\Framework\Http\Request;

/**
 * Base test case for FlintPHP applications.
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * Create the HTTP Kernel for testing.
     *
     * This forces the application to wire up its own Kernel, avoiding
     * framework-level magic and keeping tests decoupled from future changes.
     */
    abstract protected function createKernel(): Kernel;

    /**
     * Call the given URI with a GET request.
     *
     * @param array<string, string|string[]> $headers
     */
    protected function get(string $uri, array $headers = []): TestResponse
    {
        return $this->call('GET', $uri, $headers);
    }

    /**
     * Call the given URI with a POST request.
     *
     * @param array<string, string|string[]> $headers
     */
    protected function post(string $uri, string $body = '', array $headers = []): TestResponse
    {
        return $this->call('POST', $uri, $headers, $body);
    }

    /**
     * Call the given URI with a POST request, automatically encoding the
     * body as JSON and setting the Content-Type header.
     *
     * @param array<string, mixed> $data
     * @param array<string, string|string[]> $headers
     */
    protected function postJson(string $uri, array $data = [], array $headers = []): TestResponse
    {
        $headers['Content-Type'] = 'application/json';
        $body = json_encode($data, JSON_THROW_ON_ERROR);

        return $this->call('POST', $uri, $headers, $body);
    }

    /**
     * Call the given URI with the given method.
     *
     * @param array<string, string|string[]> $headers
     */
    protected function call(string $method, string $uri, array $headers = [], string $body = ''): TestResponse
    {
        $queryString = parse_url($uri, PHP_URL_QUERY);
        $query = [];
        if (is_string($queryString)) {
            parse_str($queryString, $query);
        }

        $request = new Request(
            method: strtoupper($method),
            uri: $uri,
            headers: new HeaderBag($headers),
            body: $body,
            query: $query,
        );

        $kernel = $this->createKernel();
        $response = $kernel->handle($request);

        return new TestResponse($response);
    }
}
