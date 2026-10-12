<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Queue;

use FlintPHP\Framework\Database\ConnectionInterface;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

/**
 * Durable FIFO queue backed by SQL tables `flint_jobs` and `flint_failed_jobs`.
 *
 * Only SerializableJobInterface jobs are accepted (explicit payload contract).
 */
final class DatabaseQueue implements QueueInterface
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $queue = 'default',
    ) {
        if (preg_match('/\A[a-zA-Z0-9_\-]{1,64}\z/', $this->queue) !== 1) {
            throw new InvalidArgumentException('Invalid queue name');
        }

        $this->ensureTables();
    }

    public function push(JobInterface $job): string
    {
        if (!$job instanceof SerializableJobInterface) {
            throw new InvalidArgumentException(
                'DatabaseQueue requires SerializableJobInterface (toArray/fromArray).'
            );
        }

        $id = bin2hex(random_bytes(16));
        $class = $job::class;

        try {
            $payload = json_encode($job->toArray(), JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Job payload is not JSON-serializable', 0, $e);
        }

        $this->connection->execute(
            'INSERT INTO flint_jobs (id, queue, job_class, payload, attempts, available_at, created_at)
             VALUES (?, ?, ?, ?, 0, ?, ?)',
            [$id, $this->queue, $class, $payload, gmdate('c'), gmdate('c')]
        );

        return $id;
    }

    public function pop(): ?JobInterface
    {
        $now = gmdate('c');

        $row = $this->connection->fetch(
            'SELECT id, job_class, payload, attempts FROM flint_jobs
             WHERE queue = ? AND available_at <= ?
             ORDER BY id ASC LIMIT 1',
            [$this->queue, $now]
        );

        if ($row === null) {
            return null;
        }

        $this->connection->execute('DELETE FROM flint_jobs WHERE id = ?', [$row['id']]);

        $job = $this->hydrate((string) $row['job_class'], (string) $row['payload']);

        return new ReservedJob(
            id: (string) $row['id'],
            job: $job,
            attempts: (int) $row['attempts'],
            queue: $this,
        );
    }

    public function size(): int
    {
        return (int) $this->connection->fetchColumn(
            'SELECT COUNT(*) FROM flint_jobs WHERE queue = ?',
            [$this->queue]
        );
    }

    public function clear(): void
    {
        $this->connection->execute('DELETE FROM flint_jobs WHERE queue = ?', [$this->queue]);
    }

    /**
     * Re-queue a reserved job after failure (increment attempts, optional delay).
     */
    public function release(string $id, SerializableJobInterface $job, int $attempts, int $delaySeconds = 0): void
    {
        $available = gmdate('c', time() + max(0, $delaySeconds));

        try {
            $payload = json_encode($job->toArray(), JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Job payload is not JSON-serializable', 0, $e);
        }

        $this->connection->execute(
            'INSERT INTO flint_jobs (id, queue, job_class, payload, attempts, available_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$id, $this->queue, $job::class, $payload, $attempts, $available, gmdate('c')]
        );
    }

    public function fail(string $id, SerializableJobInterface $job, int $attempts, string $error): void
    {
        try {
            $payload = json_encode($job->toArray(), JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $payload = '{}';
        }

        $this->connection->execute(
            'INSERT INTO flint_failed_jobs (id, queue, job_class, payload, attempts, error, failed_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$id, $this->queue, $job::class, $payload, $attempts, mb_substr($error, 0, 2000), gmdate('c')]
        );
    }

    private function hydrate(string $class, string $payloadJson): SerializableJobInterface
    {
        if (!class_exists($class) || !is_subclass_of($class, SerializableJobInterface::class)) {
            throw new RuntimeException(sprintf('Cannot hydrate job class %s', $class));
        }

        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Corrupt job payload', 0, $e);
        }

        if (!is_array($data)) {
            throw new RuntimeException('Job payload must be a JSON object');
        }

        return $class::fromArray($data);
    }

    private function ensureTables(): void
    {
        $this->connection->execute(
            'CREATE TABLE IF NOT EXISTS flint_jobs (
                id TEXT PRIMARY KEY NOT NULL,
                queue TEXT NOT NULL,
                job_class TEXT NOT NULL,
                payload TEXT NOT NULL,
                attempts INTEGER NOT NULL DEFAULT 0,
                available_at TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );

        $this->connection->execute(
            'CREATE TABLE IF NOT EXISTS flint_failed_jobs (
                id TEXT PRIMARY KEY NOT NULL,
                queue TEXT NOT NULL,
                job_class TEXT NOT NULL,
                payload TEXT NOT NULL,
                attempts INTEGER NOT NULL,
                error TEXT NOT NULL,
                failed_at TEXT NOT NULL
            )'
        );
    }
}
