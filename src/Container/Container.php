<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Container;

use Closure;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;

/**
 * Dependency Injection Container.
 *
 * Implements PSR-11 and provides explicit binding, singleton caching,
 * aliases, and reflection-based auto-wiring for concrete classes.
 */
final class Container implements ContainerInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $bindings = [];

    /**
     * @var array<string, bool>
     */
    private array $shared = [];

    /**
     * @var array<string, string>
     */
    private array $aliases = [];

    /**
     * @var array<string, mixed>
     */
    private array $instances = [];

    /**
     * @var array<string, true>
     */
    private array $buildStack = [];

    public function __construct()
    {
        // Bind the container to itself
        $this->instances[self::class] = $this;
        $this->instances[ContainerInterface::class] = $this;
    }

    /**
     * Bind a value or factory closure to an ID.
     */
    public function set(string $id, mixed $concrete): void
    {
        $this->bindings[$id] = $concrete;
        $this->shared[$id] = false;
        unset($this->instances[$id]);
    }

    /**
     * Bind a value or factory closure to an ID as a shared singleton.
     */
    public function singleton(string $id, mixed $concrete): void
    {
        $this->bindings[$id] = $concrete;
        $this->shared[$id] = true;
        unset($this->instances[$id]);
    }

    /**
     * Alias an abstract type (like an interface) to a concrete type.
     */
    public function bind(string $abstract, string $concrete): void
    {
        $this->aliases[$abstract] = $concrete;
    }

    /**
     * Returns true if the container can return an entry for the given identifier.
     */
    public function has(string $id): bool
    {
        $id = $this->resolveAlias($id);

        return isset($this->bindings[$id])
            || isset($this->instances[$id])
            || class_exists($id);
    }

    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @throws NotFoundException  No entry was found for **this** identifier.
     * @throws ContainerException Error while retrieving the entry.
     */
    public function get(string $id): mixed
    {
        $id = $this->resolveAlias($id);

        // 1. Check cached singletons
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        // 2. Check explicit bindings
        if (isset($this->bindings[$id])) {
            $concrete = $this->bindings[$id];

            $object = $concrete instanceof Closure
                ? $concrete($this)
                : $concrete;

            if ($this->shared[$id]) {
                $this->instances[$id] = $object;
            }

            return $object;
        }

        // 3. Fallback to Reflection-based Auto-wiring for concrete classes
        if (!class_exists($id)) {
            throw new NotFoundException(sprintf('Target [%s] is not bound and does not exist.', $id));
        }

        $object = $this->build($id);

        // Auto-wired instances are transient by default.
        // We only cache them if they were explicitly registered as singletons,
        // but if they weren't explicitly registered, they aren't singletons.
        return $object;
    }

    /**
     * Build an instance of a given class using Reflection.
     *
     * @throws ContainerException
     */
    private function build(string $concrete): object
    {
        if (isset($this->buildStack[$concrete])) {
            throw new ContainerException(sprintf('Circular dependency detected while resolving [%s].', $concrete));
        }

        $this->buildStack[$concrete] = true;

        try {
            $reflector = new ReflectionClass($concrete);

            if (!$reflector->isInstantiable()) {
                throw new ContainerException(sprintf('Target [%s] is not instantiable.', $concrete));
            }

            $constructor = $reflector->getConstructor();

            // No constructor? Just instantiate it.
            if ($constructor === null) {
                return new $concrete();
            }

            $parameters = $constructor->getParameters();
            $dependencies = $this->resolveDependencies($parameters, $concrete);

            return $reflector->newInstanceArgs($dependencies);
        } catch (ReflectionException $e) {
            throw new ContainerException(sprintf('Failed to resolve [%s]: %s', $concrete, $e->getMessage()), 0, $e);
        } finally {
            unset($this->buildStack[$concrete]);
        }
    }

    /**
     * Resolve all dependencies for a set of ReflectionParameters.
     *
     * @param \ReflectionParameter[] $parameters
     * @throws ContainerException
     */
    private function resolveDependencies(array $parameters, string $concrete): array
    {
        $results = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                // It's a class or interface dependency
                $results[] = $this->get($type->getName());
                continue;
            }

            // Un-hinted or scalar dependency
            if ($parameter->isDefaultValueAvailable()) {
                $results[] = $parameter->getDefaultValue();
                continue;
            }

            if ($parameter->allowsNull()) {
                $results[] = null;
                continue;
            }

            throw new ContainerException(
                sprintf('Unresolvable dependency resolving [%s] in class [%s]', $parameter->getName(), $concrete)
            );
        }

        return $results;
    }

    /**
     * Resolve the true ID through any layers of aliases.
     */
    private function resolveAlias(string $id): string
    {
        $visited = [];

        while (isset($this->aliases[$id])) {
            if (isset($visited[$id])) {
                throw new ContainerException(
                    sprintf('Circular alias detected: %s', implode(' -> ', array_keys($visited)) . ' -> ' . $id)
                );
            }

            $visited[$id] = true;
            $id = $this->aliases[$id];
        }

        return $id;
    }
}
