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
 * PSR-11 with explicit binding, singleton caching, aliases, and reflection
 * auto-wiring for concrete classes.
 */
final class Container implements ContainerInterface
{
    /** @var array<string, mixed> */
    private array $bindings = [];

    /** @var array<string, bool> */
    private array $shared = [];

    /** @var array<string, string> */
    private array $aliases = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /** @var array<string, true> */
    private array $buildStack = [];

    public function __construct()
    {
        $this->instances[self::class] = $this;
        $this->instances[ContainerInterface::class] = $this;
    }

    public function set(string $id, mixed $concrete): void
    {
        $this->bindings[$id] = $concrete;
        $this->shared[$id] = false;
        unset($this->instances[$id]);
    }

    public function singleton(string $id, mixed $concrete): void
    {
        $this->bindings[$id] = $concrete;
        $this->shared[$id] = true;
        unset($this->instances[$id]);
    }

    /**
     * Alias an abstract type to a concrete class name.
     *
     * Resolution is transient unless the concrete is also registered as a singleton.
     * Prefer singleton(Iface::class, fn () => new Concrete()) when one instance is required.
     */
    public function bind(string $abstract, string $concrete): void
    {
        $this->aliases[$abstract] = $concrete;
    }

    public function has(string $id): bool
    {
        $id = $this->resolveAlias($id);

        return isset($this->bindings[$id])
            || isset($this->instances[$id])
            || class_exists($id);
    }

    public function get(string $id): mixed
    {
        $id = $this->resolveAlias($id);

        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

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

        if (!class_exists($id)) {
            throw new NotFoundException(sprintf('Target [%s] is not bound and does not exist.', $id));
        }

        return $this->build($id);
    }

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

            if ($constructor === null) {
                return new $concrete();
            }

            $dependencies = $this->resolveDependencies($constructor->getParameters(), $concrete);

            return $reflector->newInstanceArgs($dependencies);
        } catch (ReflectionException $e) {
            throw new ContainerException(sprintf('Failed to resolve [%s]: %s', $concrete, $e->getMessage()), 0, $e);
        } finally {
            unset($this->buildStack[$concrete]);
        }
    }

    /**
     * @param \ReflectionParameter[] $parameters
     */
    private function resolveDependencies(array $parameters, string $concrete): array
    {
        $results = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $className = $type->getName();

                // Prefer default / null for unbound optional class deps (interfaces etc.)
                if (!$this->has($className)) {
                    if ($parameter->isDefaultValueAvailable()) {
                        $results[] = $parameter->getDefaultValue();
                        continue;
                    }

                    if ($parameter->allowsNull()) {
                        $results[] = null;
                        continue;
                    }
                }

                $results[] = $this->get($className);
                continue;
            }

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
