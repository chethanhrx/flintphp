<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Orm;

use FlintPHP\Framework\Database\ConnectionInterface;
use FlintPHP\Framework\Database\Exception\QueryException;
use FlintPHP\Framework\Orm\Exception\ModelNotFoundException;
use FlintPHP\Framework\Orm\Internal\ModelHydrator;
use InvalidArgumentException;

/**
 * @template T of Model
 */
final class ModelQueryBuilder
{
    private string $table;

    /** @var list<array{column: string, operator: string, value: mixed}> */
    private array $wheres = [];

    /** @var list<mixed> */
    private array $parameters = [];

    /** @var list<array{column: string, direction: string}> */
    private array $orders = [];

    private ?int $limit = null;

    private ?int $offset = null;

    private const OPERATORS = ['=', '!=', '<>', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN'];

    /**
     * @param class-string<T> $modelClass
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
     * where('col', $value) or where('col', '>', $value) or where('col', 'IN', [1,2]).
     *
     * @return self<T>
     */
    public function where(string $column, mixed $operatorOrValue, mixed $value = null): self
    {
        $this->validateIdentifier($column);

        if (func_num_args() === 2) {
            $this->wheres[] = ['column' => $column, 'operator' => '=', 'value' => $operatorOrValue];
            $this->parameters[] = $operatorOrValue;

            return $this;
        }

        $operator = strtoupper((string) $operatorOrValue);
        if (!in_array($operator, self::OPERATORS, true)) {
            throw new InvalidArgumentException(sprintf('Unsupported operator: %s', $operator));
        }

        if ($operator === 'IN' || $operator === 'NOT IN') {
            if (!is_array($value) || $value === []) {
                throw new InvalidArgumentException('IN/NOT IN requires a non-empty array');
            }
            $this->wheres[] = ['column' => $column, 'operator' => $operator, 'value' => $value];
            foreach ($value as $v) {
                $this->parameters[] = $v;
            }

            return $this;
        }

        $this->wheres[] = ['column' => $column, 'operator' => $operator, 'value' => $value];
        $this->parameters[] = $value;

        return $this;
    }

    /** @return self<T> */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->validateIdentifier($column);
        $direction = strtoupper($direction);
        if ($direction !== 'ASC' && $direction !== 'DESC') {
            throw new InvalidArgumentException('orderBy direction must be ASC or DESC');
        }
        $this->orders[] = ['column' => $column, 'direction' => $direction];

        return $this;
    }

    /** @return self<T> */
    public function limit(int $limit): self
    {
        if ($limit < 0) {
            throw new InvalidArgumentException('limit must be >= 0');
        }
        $this->limit = $limit;

        return $this;
    }

    /** @return self<T> */
    public function offset(int $offset): self
    {
        if ($offset < 0) {
            throw new InvalidArgumentException('offset must be >= 0');
        }
        $this->offset = $offset;

        return $this;
    }

    /** @return array<int, T> */
    public function get(): array
    {
        $rows = $this->connection->fetchAll($this->buildSelectSql(), $this->parameters);
        $models = [];
        foreach ($rows as $row) {
            $models[] = $this->hydrator->hydrate($this->modelClass, $row);
        }

        return $models;
    }

    /** @return T|null */
    public function first(): ?Model
    {
        $builder = clone $this;
        $builder->limit = 1;
        $row = $this->connection->fetch($builder->buildSelectSql(), $builder->parameters);

        return $row === null ? null : $this->hydrator->hydrate($this->modelClass, $row);
    }

    /** @return T */
    public function firstOrFail(): Model
    {
        $model = $this->first();
        if ($model === null) {
            $id = 'unknown';
            $pk = $this->modelInstance->getPrimaryKey();
            foreach ($this->wheres as $where) {
                if ($where['column'] === $pk && is_scalar($where['value'])) {
                    $id = (string) $where['value'];
                    break;
                }
            }

            throw ModelNotFoundException::forModel($this->modelClass, $id);
        }

        return $model;
    }

    public function count(): int
    {
        return (int) $this->connection->fetchColumn($this->buildAggregateSql('COUNT(*)'), $this->parameters);
    }

    public function exists(): bool
    {
        return $this->connection->fetchColumn($this->buildAggregateSql('1') . ' LIMIT 1', $this->parameters) !== false;
    }

    private function buildSelectSql(): string
    {
        $sql = sprintf('SELECT * FROM %s', $this->quote($this->table));
        $sql = $this->appendWheres($sql);

        if ($this->orders !== []) {
            $parts = [];
            foreach ($this->orders as $order) {
                $parts[] = sprintf('%s %s', $this->quote($order['column']), $order['direction']);
            }
            $sql .= ' ORDER BY ' . implode(', ', $parts);
        }

        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }
        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }

        return $sql;
    }

    private function buildAggregateSql(string $expression): string
    {
        return $this->appendWheres(sprintf('SELECT %s FROM %s', $expression, $this->quote($this->table)));
    }

    private function appendWheres(string $sql): string
    {
        if ($this->wheres === []) {
            return $sql;
        }

        $clauses = [];
        foreach ($this->wheres as $where) {
            $col = $this->quote($where['column']);
            $op = $where['operator'];
            if ($op === 'IN' || $op === 'NOT IN') {
                $placeholders = implode(', ', array_fill(0, count($where['value']), '?'));
                $clauses[] = sprintf('%s %s (%s)', $col, $op, $placeholders);
            } else {
                $clauses[] = sprintf('%s %s ?', $col, $op);
            }
        }

        return $sql . ' WHERE ' . implode(' AND ', $clauses);
    }

    private function quote(string $identifier): string
    {
        return '"' . $identifier . '"';
    }

    private function validateIdentifier(string $identifier): void
    {
        if (preg_match('/\A[a-zA-Z0-9_]+\z/', $identifier) !== 1) {
            throw new QueryException(sprintf('Invalid SQL identifier: %s', $identifier));
        }
    }
}
