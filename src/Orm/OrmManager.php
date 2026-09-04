<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Orm;

use FlintPHP\Framework\Database\ConnectionInterface;
use FlintPHP\Framework\Database\Exception\QueryException;
use FlintPHP\Framework\Orm\Exception\MassAssignmentException;
use FlintPHP\Framework\Orm\Exception\ModelNotFoundException;
use FlintPHP\Framework\Orm\Exception\OrmException;
use FlintPHP\Framework\Orm\Internal\ModelHydrator;
use ReflectionClass;

final class OrmManager
{
    private readonly ModelHydrator $hydrator;

    public function __construct(private readonly ConnectionInterface $connection)
    {
        $this->hydrator = new ModelHydrator();
    }

    /**
     * Find a model by its primary key.
     *
     * @template T of Model
     * @param class-string<T> $modelClass
     * @param mixed $id
     * @return T|null
     * @throws OrmException
     */
    public function find(string $modelClass, mixed $id): ?Model
    {
        $instance = $this->allocateModel($modelClass);

        return $this->query($modelClass)->where($instance->getPrimaryKey(), $id)->first();
    }

    /**
     * Find a model by its primary key or throw an exception.
     *
     * @template T of Model
     * @param class-string<T> $modelClass
     * @param mixed $id
     * @return T
     * @throws ModelNotFoundException
     * @throws OrmException
     */
    public function findOrFail(string $modelClass, mixed $id): Model
    {
        $instance = $this->allocateModel($modelClass);

        return $this->query($modelClass)->where($instance->getPrimaryKey(), $id)->firstOrFail();
    }

    /**
     * Save a model to the database (INSERT or UPDATE).
     *
     * @param Model $model
     * @return bool
     * @throws OrmException
     */
    public function save(Model $model): bool
    {
        $primaryKey = $model->getPrimaryKey();
        $attributes = $this->hydrator->extract($model);

        $isNew = !array_key_exists($primaryKey, $attributes) || $attributes[$primaryKey] === null;

        if ($isNew) {
            return $this->performInsert($model, $attributes);
        }

        return $this->performUpdate($model, $attributes);
    }

    /**
     * Delete a model from the database.
     *
     * @param Model $model
     * @return bool
     * @throws OrmException
     */
    public function delete(Model $model): bool
    {
        $primaryKey = $model->getPrimaryKey();
        $attributes = $this->hydrator->extract($model);

        if (!array_key_exists($primaryKey, $attributes) || $attributes[$primaryKey] === null) {
            // Cannot delete a model without a primary key
            return false;
        }

        $table = $model->getTable();
        $this->validateIdentifier($table);
        $this->validateIdentifier($primaryKey);

        $sql = sprintf('DELETE FROM %s WHERE %s = ?', $table, $primaryKey);
        
        $affected = $this->connection->execute($sql, [$attributes[$primaryKey]]);
        
        return $affected > 0;
    }

    /**
     * Safely fill a model with an array of attributes.
     *
     * @param Model $model
     * @param array<string, mixed> $attributes
     * @throws MassAssignmentException
     */
    public function fill(Model $model, array $attributes): void
    {
        $fillable = $model->getFillable();

        if (empty($fillable)) {
            throw new MassAssignmentException(sprintf('Model [%s] does not define any fillable attributes.', $model::class));
        }

        $safeAttributes = [];
        foreach ($attributes as $key => $value) {
            if (in_array($key, $fillable, true)) {
                $safeAttributes[$key] = $value;
            }
        }

        // We use the hydrator to inject the safe values
        // Note: Hydrator extracts from DB usually, but we can reuse its property setter capability
        // However, hydrate instantiates a new object.
        // Let's manually set them using reflection to avoid a new instantiation.
        
        try {
            $reflectionClass = new ReflectionClass($model);
            
            foreach ($safeAttributes as $key => $value) {
                if ($reflectionClass->hasProperty($key)) {
                    $property = $reflectionClass->getProperty($key);
                    $property->setValue($model, $value);
                }
            }
        } catch (\ReflectionException $e) {
            throw new OrmException('Failed to fill model properties.', 0, $e);
        }
    }

    /**
     * Create a new query builder for the given model class.
     *
     * @template T of Model
     * @param class-string<T> $modelClass
     * @return ModelQueryBuilder<T>
     * @throws OrmException
     */
    public function query(string $modelClass): ModelQueryBuilder
    {
        $instance = $this->allocateModel($modelClass);

        return new ModelQueryBuilder($this->connection, $this->hydrator, $modelClass, $instance);
    }

    /**
     * Allocate a dummy instance of the model for metadata extraction.
     *
     * @template T of Model
     * @param class-string<T> $modelClass
     * @return T
     * @throws OrmException
     */
    private function allocateModel(string $modelClass): Model
    {
        try {
            $reflection = new ReflectionClass($modelClass);
            
            /** @var T */
            return $reflection->newInstanceWithoutConstructor();
        } catch (\ReflectionException $e) {
            throw new OrmException(sprintf('Could not instantiate model [%s].', $modelClass), 0, $e);
        }
    }

    /**
     * Perform an INSERT operation.
     *
     * @param Model $model
     * @param array<string, mixed> $attributes
     * @return bool
     */
    private function performInsert(Model $model, array $attributes): bool
    {
        $table = $model->getTable();
        $this->validateIdentifier($table);

        $columns = [];
        $placeholders = [];
        $values = [];

        foreach ($attributes as $column => $value) {
            $this->validateIdentifier($column);
            $columns[] = $column;
            $placeholders[] = '?';
            $values[] = $value;
        }

        if (empty($columns)) {
            // Cannot insert without values. Actually, some DBs support 'DEFAULT VALUES', 
            // but we'll return false for now.
            return false;
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $affected = $this->connection->execute($sql, $values);

        if ($affected > 0) {
            // Try to set the auto-increment ID if the DB provides one
            $lastId = $this->connection->pdo()->lastInsertId();
            
            if ($lastId !== '0' && $lastId !== '') {
                $primaryKey = $model->getPrimaryKey();
                
                try {
                    $reflectionClass = new ReflectionClass($model);
                    if ($reflectionClass->hasProperty($primaryKey)) {
                        $property = $reflectionClass->getProperty($primaryKey);
                        // Cast to int if typed as int
                        $type = $property->getType();
                        if ($type !== null && method_exists($type, 'getName') && $type->getName() === 'int') {
                            $property->setValue($model, (int) $lastId);
                        } else {
                            $property->setValue($model, $lastId);
                        }
                    }
                } catch (\ReflectionException) {
                    // Ignore if we can't set the ID
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Perform an UPDATE operation.
     *
     * @param Model $model
     * @param array<string, mixed> $attributes
     * @return bool
     */
    private function performUpdate(Model $model, array $attributes): bool
    {
        $table = $model->getTable();
        $primaryKey = $model->getPrimaryKey();
        
        $this->validateIdentifier($table);
        $this->validateIdentifier($primaryKey);

        $primaryKeyValue = $attributes[$primaryKey];
        unset($attributes[$primaryKey]);

        if (empty($attributes)) {
            // Nothing to update
            return true;
        }

        $setClauses = [];
        $values = [];

        foreach ($attributes as $column => $value) {
            $this->validateIdentifier($column);
            $setClauses[] = sprintf('%s = ?', $column);
            $values[] = $value;
        }

        // Add the primary key for the WHERE clause
        $values[] = $primaryKeyValue;

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s = ?',
            $table,
            implode(', ', $setClauses),
            $primaryKey
        );

        $affected = $this->connection->execute($sql, $values);

        // Even if affected is 0, the query succeeded (maybe no values actually changed)
        // We consider it a success as long as it didn't throw a QueryException.
        // Wait, if the record doesn't exist anymore, affected is 0. 
        // Eloquent returns true anyway for `save()`, but strict mapping might want to know.
        // We'll return true.
        return true;
    }

    /**
     * Validate an identifier to prevent SQL injection.
     *
     * @param string $identifier
     * @throws QueryException
     */
    private function validateIdentifier(string $identifier): void
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $identifier)) {
            throw new QueryException(sprintf('Invalid SQL identifier: %s', $identifier));
        }
    }
}
