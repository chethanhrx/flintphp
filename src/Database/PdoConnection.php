<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Database;

use FlintPHP\Framework\Database\Exception\ConnectionException;
use FlintPHP\Framework\Database\Exception\QueryException;
use FlintPHP\Framework\Database\Exception\TransactionException;
use PDO;
use PDOException;
use PDOStatement;
use Throwable;
use SensitiveParameter;

final class PdoConnection implements ConnectionInterface
{
    private ?PDO $pdo = null;
    private bool $inTransaction = false;

    /**
     * @param array<int, mixed> $options
     */
    public function __construct(
        private readonly string $dsn,
        private readonly string $username = '',
        #[SensitiveParameter] private readonly string $password = '',
        private readonly array $options = []
    ) {
    }

    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $this->connect();
        }

        return $this->pdo;
    }

    private function connect(): void
    {
        $options = $this->options + [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($this->dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            throw new ConnectionException('Could not connect to the database.', 0, $e);
        }
    }

    public function execute(string $sql, array $parameters = []): int
    {
        $stmt = $this->prepareStatement($sql, $parameters);

        return $stmt->rowCount();
    }

    public function fetchAll(string $sql, array $parameters = []): array
    {
        $stmt = $this->prepareStatement($sql, $parameters);

        return $stmt->fetchAll();
    }

    public function fetch(string $sql, array $parameters = []): ?array
    {
        $stmt = $this->prepareStatement($sql, $parameters);

        $result = $stmt->fetch();

        return $result === false ? null : $result;
    }

    public function fetchColumn(string $sql, array $parameters = []): mixed
    {
        $stmt = $this->prepareStatement($sql, $parameters);

        return $stmt->fetchColumn();
    }

    private function prepareStatement(string $sql, array $parameters): PDOStatement
    {
        try {
            $pdo = $this->pdo();
            $stmt = $pdo->prepare($sql);

            if ($stmt === false) {
                throw new QueryException(sprintf('Could not prepare SQL statement: %s', $sql));
            }

            foreach ($parameters as $key => $value) {
                // PDO is 1-indexed for positional parameters if they pass a 0-indexed array.
                $bindKey = is_int($key) ? $key + 1 : $key;

                if (is_array($value) || is_object($value)) {
                    throw new QueryException('Array or object parameters are not supported.');
                }

                $type = match (true) {
                    is_int($value) => PDO::PARAM_INT,
                    is_bool($value) => PDO::PARAM_BOOL,
                    $value === null => PDO::PARAM_NULL,
                    default => PDO::PARAM_STR,
                };

                $stmt->bindValue($bindKey, $value, $type);
            }

            $stmt->execute();

            return $stmt;
        } catch (PDOException $e) {
            throw new QueryException(sprintf('Query failed: %s (SQL: %s)', $e->getMessage(), $sql), 0, $e);
        }
    }

    public function begin(): void
    {
        if ($this->inTransaction) {
            throw new TransactionException('Nested transactions are not supported.');
        }

        try {
            $this->pdo()->beginTransaction();
            $this->inTransaction = true;
        } catch (PDOException $e) {
            throw new TransactionException('Failed to begin transaction.', 0, $e);
        }
    }

    public function commit(): void
    {
        if (!$this->inTransaction) {
            throw new TransactionException('There is no active transaction to commit.');
        }

        try {
            $this->pdo()->commit();
            $this->inTransaction = false;
        } catch (PDOException $e) {
            $this->inTransaction = false;
            throw new TransactionException('Failed to commit transaction.', 0, $e);
        }
    }

    public function rollBack(): void
    {
        if (!$this->inTransaction) {
            throw new TransactionException('There is no active transaction to rollback.');
        }

        try {
            $this->pdo()->rollBack();
            $this->inTransaction = false;
        } catch (PDOException $e) {
            $this->inTransaction = false;
            throw new TransactionException('Failed to rollback transaction.', 0, $e);
        }
    }

    public function inTransaction(): bool
    {
        return $this->inTransaction;
    }

    public function transaction(callable $callback): mixed
    {
        $this->begin();

        try {
            $result = $callback();
            $this->commit();

            return $result;
        } catch (Throwable $e) {
            try {
                $this->rollBack();
            } catch (Throwable $rollbackException) {
                // Preserve the original exception.
            }

            throw $e;
        }
    }
}
