<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Routing;

use Closure;
use FlintPHP\Framework\Container\Container;
use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;
use FlintPHP\Framework\Routing\Exception\InvalidHandlerException;
use FlintPHP\Framework\Routing\Exception\UnresolvableParameterException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Resolves and invokes route handlers.
 *
 * Uses Reflection and the DI Container to automatically inject the
 * Request, route parameters, and resolved services into the controller method.
 */
final class HandlerInvoker
{
    public function __construct(
        private readonly Container $container,
    ) {
    }

    /**
     * Resolves and executes the handler.
     *
     * @param mixed   $handler         The raw route handler.
     * @param Request $request         The current HTTP Request.
     * @param array   $routeParameters Matched route parameters (e.g. ['id' => '42']).
     *
     * @return Response
     *
     * @throws InvalidHandlerException        If the handler is not callable or returns the wrong type.
     * @throws UnresolvableParameterException If a method dependency cannot be resolved.
     */
    public function invoke(mixed $handler, Request $request, array $routeParameters): Response
    {
        $callable = $this->resolveCallable($handler);

        $parameters = $this->resolveParameters($callable, $request, $routeParameters);

        $response = $callable(...$parameters);

        if (!$response instanceof Response) {
            throw new InvalidHandlerException(
                sprintf('Handler must return an instance of %s. Got: %s', Response::class, get_debug_type($response))
            );
        }

        return $response;
    }

    /**
     * Converts the raw handler into a strictly executable PHP callable.
     */
    private function resolveCallable(mixed $handler): callable
    {
        // 1. Array format: [ControllerClass, 'method']
        if (is_array($handler) && count($handler) === 2 && is_string($handler[0])) {
            $controller = $this->container->get($handler[0]);
            $handler = [$controller, $handler[1]];
        }

        // 2. String format: ControllerClass (invokable)
        if (is_string($handler) && class_exists($handler)) {
            $handler = $this->container->get($handler);
        }

        // 3. Ensure it is callable
        if (!is_callable($handler)) {
            throw new InvalidHandlerException('The provided route handler is not callable.');
        }

        return $handler;
    }

    /**
     * Analyzes the callable and resolves its required arguments via Reflection.
     */
    private function resolveParameters(callable $callable, Request $request, array $routeParameters): array
    {
        $reflector = $this->getReflector($callable);

        $resolvedArgs = [];

        foreach ($reflector->getParameters() as $parameter) {
            $resolvedArgs[] = $this->resolveParameter($parameter, $request, $routeParameters);
        }

        return $resolvedArgs;
    }

    /**
     * Resolves a single ReflectionParameter according to precedence rules.
     */
    private function resolveParameter(ReflectionParameter $parameter, Request $request, array $routeParameters): mixed
    {
        $name = $parameter->getName();
        $type = $parameter->getType();

        // 1. Framework Objects / Container Services
        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            $className = $type->getName();

            if ($className === Request::class) {
                return $request;
            }

            if ($this->container->has($className)) {
                return $this->container->get($className);
            }

            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            if ($parameter->allowsNull()) {
                return null;
            }

            throw new UnresolvableParameterException(
                sprintf('Cannot resolve class dependency %s for parameter $%s.', $className, $name)
            );
        }

        // 2. Route Parameter (Name match)
        if (array_key_exists($name, $routeParameters)) {
            return $this->castRouteParameter($routeParameters[$name], $name, $type);
        }

        // 3. Route Parameter (Bulk Array for backward compatibility)
        if ($type instanceof ReflectionNamedType && $type->getName() === 'array') {
            return $routeParameters;
        }

        // 4. Default Value
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        // 5. Nullable
        if ($parameter->allowsNull()) {
            return null;
        }

        // 6. Failure
        throw new UnresolvableParameterException(
            sprintf('Cannot resolve parameter $%s for route handler.', $name)
        );
    }

    /**
     * Casts a string route parameter to the requested type if strictly defined.
     */
    private function castRouteParameter(string $value, string $name, ?\ReflectionType $type): mixed
    {
        if ($type instanceof ReflectionNamedType) {
            $typeName = $type->getName();

            if ($typeName === 'int') {
                if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                    throw new UnresolvableParameterException(sprintf('Route parameter $%s must be an integer, got "%s"', $name, $value));
                }
                return (int) $value;
            }

            if ($typeName === 'float') {
                if (filter_var($value, FILTER_VALIDATE_FLOAT) === false) {
                    throw new UnresolvableParameterException(sprintf('Route parameter $%s must be a float, got "%s"', $name, $value));
                }
                return (float) $value;
            }

            if ($typeName === 'bool') {
                $filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($filtered === null) {
                    throw new UnresolvableParameterException(sprintf('Route parameter $%s must be a boolean, got "%s"', $name, $value));
                }
                return $filtered;
            }
            
            if ($typeName === 'array') {
                throw new UnresolvableParameterException(sprintf('Route parameter $%s cannot be cast to array', $name));
            }
        }

        return $value;
    }

    /**
     * Obtains the correct Reflection abstract based on the callable type.
     */
    private function getReflector(callable $callable): ReflectionFunction|ReflectionMethod
    {
        if ($callable instanceof Closure) {
            return new ReflectionFunction($callable);
        }

        if (is_string($callable) && str_contains($callable, '::')) {
            return new ReflectionMethod($callable);
        }

        if (is_array($callable)) {
            return new ReflectionMethod($callable[0], $callable[1]);
        }

        if (is_object($callable) && method_exists($callable, '__invoke')) {
            return new ReflectionMethod($callable, '__invoke');
        }

        return new ReflectionFunction($callable);
    }
}
