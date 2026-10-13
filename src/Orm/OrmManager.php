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
     * @template T of Model
     * @param class-string<T> $modelClass
     * @return T|null
     */
    public function find(string $modelClass, mixed $id): ?Model
    {
        $instance = $this->allocateModel($modelClass);

        return $this->query($modelClass)->where($instance->getPrimaryKey(), $id)->first();
    }

    /**
     * @template T of Model
     * @param class-string<T> $modelClass
     * @return T
     */
    public function findOrFail(string $modelClass, mixed $id): Model
    {
        $instance = $this->allocateModel($modelClass);

        return $this->query($modelClass)->where($instance->getPrimaryKey(), $id)->firstOrFail();
    }

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

    public function delete(Model $model): bool
    {
        $primaryKey = $model->getPrimaryKey();
        $attributes = $this->hydrator->extract($model);

        if (!array_key_exists($primaryKey, $attributes) || $attributes[$primaryKey] === null) {
            return false;
        }

        $table = $model->getTable();
        $this->validateIdentifier($table);
        $this->validateIdentifier($primaryKey);

        $sql = sprintf('DELETE FROM %s WHERE %s = ?', $this->quoteIdentifier($table), $this->quoteIdentifier($primaryKey));
        $affected = $this->connection->execute($sql, [$attributes[$primaryKey]]);

        return $affected > 0;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function fill(Model $model, array $attributes): void
    {
        $fillable = $model->getFillable();

        if ($fillable === []) {
            throw new MassAssignmentException(sprintf('Model [%s] does not define any fillable attributes.', $model::class));
        }

        $safeAttributes = [];
        foreach ($attributes as $key => $value) {
            if (in_array($key, $fillable, true)) {
                $safeAttributes[$key] = $value;
            }
        }

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
     * @template T of Model
     * @param class-string<T> $modelClass
     * @return ModelQueryBuilder<T>
     */
    public function query(string $modelClass): ModelQueryBuilder
    {
        $instance = $this->allocateModel($modelClass);

        return new ModelQueryBuilder($this->connection, $this->hydrator, $modelClass, $instance);
    }

    /**
     * @template T of Model
     * @param class-string<T> $modelClass
     * @return T
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
     * @param array<string, mixed> $attributes
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
            $columns[] = $this->quoteIdentifier($column);
            $placeholders[] = '?';
            $values[] = $value;
        }

        if ($columns === []) {
            return false;
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->quoteIdentifier($table),
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $affected = $this->connection->execute($sql, $values);

        if ($affected > 0) {
            $lastId = $this->connection->pdo()->lastInsertId();

            if ($lastId !== '0' && $lastId !== '') {
                $primaryKey = $model->getPrimaryKey();

                try {
                    $reflectionClass = new ReflectionClass($model);
                    if ($reflectionClass->hasProperty($primaryKey)) {
                        $property = $reflectionClass->getProperty($primaryKey);
                        $type = $property->getType();
                        if ($type !== null && method_exists($type, 'getName') && $type->getName() === 'int') {
                            $property->setValue($model, (int) $lastId);
                        } else {
                            $property->setValue($model, $lastId);
                        }
                    }
                } catch (\ReflectionException) {
                    // ignore
                }
            }

            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function performUpdate(Model $model, array $attributes): bool
    {
        $table = $model->getTable();
        $primaryKey = $model->getPrimaryKey();

        $this->validateIdentifier($table);
        $this->validateIdentifier($primaryKey);

        $primaryKeyValue = $attributes[$primaryKey];
        unset($attributes[$primaryKey]);

        if ($attributes === []) {
            return true;
        }

        $setClauses = [];
        $values = [];

        foreach ($attributes as $column => $value) {
            $this->validateIdentifier($column);
            $setClauses[] = sprintf('%s = ?', $this->quoteIdentifier($column));
            $values[] = $value;
        }

        $values[] = $primaryKeyValue;

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s = ?',
            $this->quoteIdentifier($table),
            implode(', ', $setClauses),
            $this->quoteIdentifier($primaryKey)
        );

        $this->connection->execute($sql, $values);

        // Success means the statement executed without error (0 rows affected is still OK).
        return true;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return SqlIdentifierQuoter::quote(
            $identifier,
            (string) $this->connection->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME),
        );
    }

    private function validateIdentifier(string $identifier): void
    {
        if (preg_match('/\A[a-zA-Z0-9_]+\z/', $identifier) !== 1) {
            throw new QueryException(sprintf('Invalid SQL identifier: %s', $identifier));
        }
    }
}
