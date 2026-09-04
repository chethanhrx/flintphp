<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Orm;

use FlintPHP\Framework\Database\ConnectionInterface;
use FlintPHP\Framework\Database\Exception\QueryException;
use FlintPHP\Framework\Orm\Exception\ModelNotFoundException;
use FlintPHP\Framework\Orm\Internal\ModelHydrator;

/**
 * @template T of Model
 */
final class ModelQueryBuilder
{
    private string $table;
    
    /** @var array<int, array{column: string, value: mixed}> */
    private array $wheres = [];
    
    /** @var array<int, mixed> */
    private array $parameters = [];

    /**
     * @param ConnectionInterface $connection
     * @param ModelHydrator $hydrator
     * @param class-string<T> $modelClass
     * @param Model $modelInstance
     */
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly ModelHydrator $hydrator,
        private readonly string $modelClass,
        private readonly Model $modelInstance
    ) {
        $this->table = $this->modelInstance->getTable();
        $this->validateIdentifier($this->table);
    }

    /**
     * Add a basic where clause to the query.
     *
     * @param string $column
     * @param mixed $value
     * @return self<T>
     */
    public function where(string $column, mixed $value): self
    {
        $this->validateIdentifier($column);
        $this->wheres[] = ['column' => $column, 'value' => $value];
        $this->parameters[] = $value;
        
        return $this;
    }

    /**
     * Execute the query and get all results.
     *
     * @return array<int, T>
     */
    public function get(): array
    {
        $sql = $this->buildSelectSql();
        $rows = $this->connection->fetchAll($sql, $this->parameters);

        $models = [];
        foreach ($rows as $row) {
            $models[] = $this->hydrator->hydrate($this->modelClass, $row);
        }

        return $models;
    }

    /**
     * Execute the query and get the first result.
     *
     * @return T|null
     */
    public function first(): ?Model
    {
        $sql = $this->buildSelectSql() . ' LIMIT 1';
        $row = $this->connection->fetch($sql, $this->parameters);

        if ($row === null) {
            return null;
        }

        return $this->hydrator->hydrate($this->modelClass, $row);
    }

    /**
     * Execute the query and get the first result or throw an exception.
     *
     * @return T
     * @throws ModelNotFoundException
     */
    public function firstOrFail(): Model
    {
        $model = $this->first();

        if ($model === null) {
            // To provide a useful error, we extract the primary key if it was used in where
            $id = 'unknown';
            $pk = $this->modelInstance->getPrimaryKey();
            foreach ($this->wheres as $where) {
                if ($where['column'] === $pk) {
                    $id = $where['value'];
                    break;
                }
            }
            
            throw ModelNotFoundException::forModel($this->modelClass, $id);
        }

        return $model;
    }

    /**
     * Retrieve the count of the records matching the query.
     *
     * @return int
     */
    public function count(): int
    {
        $sql = $this->buildAggregateSql('COUNT(*)');
        
        return (int) $this->connection->fetchColumn($sql, $this->parameters);
    }

    /**
     * Determine if any rows exist for the current query.
     *
     * @return bool
     */
    public function exists(): bool
    {
        // Simple select 1 limit 1
        $sql = $this->buildAggregateSql('1') . ' LIMIT 1';
        
        return $this->connection->fetchColumn($sql, $this->parameters) !== false;
    }

    /**
     * Build the basic SELECT SQL statement.
     */
    private function buildSelectSql(): string
    {
        $sql = sprintf('SELECT * FROM %s', $this->table);

        return $this->appendWheres($sql);
    }

    /**
     * Build an aggregate SELECT SQL statement.
     */
    private function buildAggregateSql(string $expression): string
    {
        // We only allow hardcoded aggregate expressions internally (COUNT(*), 1)
        $sql = sprintf('SELECT %s FROM %s', $expression, $this->table);

        return $this->appendWheres($sql);
    }

    /**
     * Append the WHERE clauses to the SQL statement.
     */
    private function appendWheres(string $sql): string
    {
        if (count($this->wheres) === 0) {
            return $sql;
        }

        $clauses = [];
        foreach ($this->wheres as $where) {
            $clauses[] = sprintf('%s = ?', $where['column']);
        }

        return $sql . ' WHERE ' . implode(' AND ', $clauses);
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
