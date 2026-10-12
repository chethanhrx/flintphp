<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Routing;

use Closure;
use FlintPHP\Framework\Container\Container;
use FlintPHP\Framework\Http\Exception\HttpException;
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
 * Supported handler forms:
 * - Closure / callable
 * - [ControllerClass::class, 'method']
 * - Invokable ControllerClass::class
 *
 * String "Controller@method" is intentionally NOT supported.
 */
final class HandlerInvoker
{
    public function __construct(
        private readonly Container $container,
    ) {
    }

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

    private function resolveCallable(mixed $handler): callable
    {
        if (is_array($handler) && count($handler) === 2 && is_string($handler[0])) {
            $controller = $this->container->get($handler[0]);
            $handler = [$controller, $handler[1]];
        }

        if (is_string($handler) && class_exists($handler)) {
            $handler = $this->container->get($handler);
        }

        if (!is_callable($handler)) {
            throw new InvalidHandlerException(
                'The provided route handler is not callable. '
                . 'Use a Closure, [Controller::class, \'method\'], or an invokable class. '
                . 'String "Controller@method" is not supported.'
            );
        }

        return $handler;
    }

    private function resolveParameters(callable $callable, Request $request, array $routeParameters): array
    {
        $reflector = $this->getReflector($callable);
        $resolvedArgs = [];

        foreach ($reflector->getParameters() as $parameter) {
            $resolvedArgs[] = $this->resolveParameter($parameter, $request, $routeParameters);
        }

        return $resolvedArgs;
    }

    private function resolveParameter(ReflectionParameter $parameter, Request $request, array $routeParameters): mixed
    {
        $name = $parameter->getName();
        $type = $parameter->getType();

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

        if (array_key_exists($name, $routeParameters)) {
            return $this->castRouteParameter($routeParameters[$name], $name, $type);
        }

        if ($type instanceof ReflectionNamedType && $type->getName() === 'array') {
            return $routeParameters;
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if ($parameter->allowsNull()) {
            return null;
        }

        throw new UnresolvableParameterException(
            sprintf('Cannot resolve parameter $%s for route handler.', $name)
        );
    }

    /**
     * Cast route parameters. Invalid casts are client errors (422), not server errors.
     */
    private function castRouteParameter(string $value, string $name, ?\ReflectionType $type): mixed
    {
        if ($type instanceof ReflectionNamedType) {
            $typeName = $type->getName();

            if ($typeName === 'int') {
                if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                    throw new HttpException(
                        422,
                        sprintf('Route parameter "%s" must be an integer.', $name)
                    );
                }

                return (int) $value;
            }

            if ($typeName === 'float') {
                if (filter_var($value, FILTER_VALIDATE_FLOAT) === false) {
                    throw new HttpException(
                        422,
                        sprintf('Route parameter "%s" must be a float.', $name)
                    );
                }

                return (float) $value;
            }

            if ($typeName === 'bool') {
                $filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($filtered === null) {
                    throw new HttpException(
                        422,
                        sprintf('Route parameter "%s" must be a boolean.', $name)
                    );
                }

                return $filtered;
            }

            if ($typeName === 'array') {
                throw new HttpException(
                    422,
                    sprintf('Route parameter "%s" cannot be cast to array.', $name)
                );
            }
        }

        return $value;
    }

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
