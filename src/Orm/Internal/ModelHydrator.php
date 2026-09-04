<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Orm\Internal;

use FlintPHP\Framework\Orm\Exception\OrmException;
use FlintPHP\Framework\Orm\Model;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

/**
 * @internal
 */
final class ModelHydrator
{
    /**
     * @var array<string, ReflectionClass<Model>>
     */
    private array $classCache = [];

    /**
     * @var array<string, array<string, ReflectionProperty>>
     */
    private array $propertyCache = [];

    /**
     * Hydrate a model instance with data.
     *
     * @template T of Model
     * @param class-string<T> $modelClass
     * @param array<string, mixed> $data
     * @return T
     * @throws OrmException
     */
    public function hydrate(string $modelClass, array $data): Model
    {
        try {
            $reflectionClass = $this->getReflectionClass($modelClass);
            
            /** @var T $instance */
            $instance = $reflectionClass->newInstanceWithoutConstructor();

            foreach ($data as $column => $value) {
                $property = $this->getReflectionProperty($modelClass, $column);
                
                if ($property !== null) {
                    // In PHP 8.1+, ReflectionProperty::setValue() automatically coerces types
                    // if strict_types is not enabled in the calling context, or we can just pass it directly.
                    // For safety, we just assign it. Uninitialized properties are populated.
                    $property->setValue($instance, $value);
                }
            }

            return $instance;
        } catch (ReflectionException $e) {
            throw new OrmException(sprintf('Failed to hydrate model [%s]: %s', $modelClass, $e->getMessage()), 0, $e);
        }
    }

    /**
     * Extract attributes from a model instance.
     *
     * @param Model $model
     * @return array<string, mixed>
     */
    public function extract(Model $model): array
    {
        $class = $model::class;
        $properties = $this->getReflectionProperties($class);
        
        $data = [];
        
        foreach ($properties as $name => $property) {
            if ($property->isInitialized($model)) {
                $data[$name] = $property->getValue($model);
            }
        }

        return $data;
    }

    /**
     * Get or cache the ReflectionClass for a model.
     *
     * @template T of Model
     * @param class-string<T> $class
     * @return ReflectionClass<T>
     * @throws ReflectionException
     */
    private function getReflectionClass(string $class): ReflectionClass
    {
        if (!isset($this->classCache[$class])) {
            $this->classCache[$class] = new ReflectionClass($class);
        }

        /** @var ReflectionClass<T> */
        return $this->classCache[$class];
    }

    /**
     * Get or cache a single ReflectionProperty.
     *
     * @param string $class
     * @param string $property
     * @return ReflectionProperty|null
     */
    private function getReflectionProperty(string $class, string $property): ?ReflectionProperty
    {
        $properties = $this->getReflectionProperties($class);

        return $properties[$property] ?? null;
    }

    /**
     * Get or cache all ReflectionProperties for a class.
     *
     * @param string $class
     * @return array<string, ReflectionProperty>
     */
    private function getReflectionProperties(string $class): array
    {
        if (!isset($this->propertyCache[$class])) {
            try {
                $reflection = $this->getReflectionClass($class);
            } catch (ReflectionException) {
                return [];
            }

            $properties = [];
            // Only public properties are considered persistable attributes.
            // This natively protects all protected/private framework metadata.
            foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
                if (!$prop->isStatic()) {
                    $properties[$prop->getName()] = $prop;
                }
            }

            $this->propertyCache[$class] = $properties;
        }

        return $this->propertyCache[$class];
    }
}
