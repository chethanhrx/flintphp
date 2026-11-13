<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Queue;

use DateTimeImmutable;
use DateTimeZone;
use FlintPHP\Framework\Database\ConnectionInterface;
use FlintPHP\Framework\Database\Exception\QueryException;
use InvalidArgumentException;
use JsonException;
use PDOException;
use RuntimeException;

final class DatabaseQueue implements QueueInterface
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $queue = 'default',
        private readonly int $reservationSeconds = 60,
    ) {
        if (preg_match('/\A[a-zA-Z0-9_\-]{1,64}\z/', $this->queue) !== 1) {
            throw new InvalidArgumentException('Invalid queue name');
        }
        if ($this->reservationSeconds < 1) {
            throw new InvalidArgumentException('reservationSeconds must be at least 1.');
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
        try {
            $payload = json_encode($job->toArray(), JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Job payload is not JSON-serializable', 0, $e);
        }

        $now = $this->timestamp();
        $this->connection->execute(
            'INSERT INTO flint_jobs
                (id, queue, job_class, payload, attempts, available_at, created_at, reserved_until, reservation_token)
             VALUES (?, ?, ?, ?, 0, ?, ?, NULL, NULL)',
            [$id, $this->queue, $job::class, $payload, $now, $now]
        );

        return $id;
    }

    /**
     * Reserve the oldest available job for this worker.
     *
     * Reservations expire automatically, so a job becomes visible again if a
     * worker exits without acknowledging, releasing, or failing it. Processing
     * is therefore at-least-once: handlers should be idempotent.
     */
    public function pop(): ?JobInterface
    {
        while (true) {
            $now = $this->timestamp();
            $row = $this->connection->fetch(
                'SELECT id, job_class, payload, attempts
                 FROM flint_jobs
                 WHERE queue = ?
                   AND available_at <= ?
                   AND (reserved_until IS NULL OR reserved_until <= ?)
                 ORDER BY created_at ASC, id ASC
                 LIMIT 1',
                [$this->queue, $now, $now]
            );

            if ($row === null) {
                return null;
            }

            $token = bin2hex(random_bytes(16));
            $reservedUntil = $this->timestampAfter($this->reservationSeconds);
            $claimed = $this->connection->execute(
                'UPDATE flint_jobs
                 SET reservation_token = ?, reserved_until = ?, attempts = attempts + 1
                 WHERE id = ? AND queue = ? AND available_at <= ?
                   AND (reserved_until IS NULL OR reserved_until <= ?)',
                [$token, $reservedUntil, $row['id'], $this->queue, $now, $now]
            );

            if ($claimed !== 1) {
                // Another worker won the conditional update; look for another job.
                continue;
            }

            $job = $this->hydrate((string) $row['job_class'], (string) $row['payload']);

            return new ReservedJob(
                id: (string) $row['id'],
                job: $job,
                attempts: (int) $row['attempts'] + 1,
                reservationToken: $token,
                queue: $this,
            );
        }
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

    /** Remove a successfully processed job only if this worker still owns it. */
    public function acknowledge(string $id, string $reservationToken): void
    {
        $deleted = $this->connection->execute(
            'DELETE FROM flint_jobs WHERE id = ? AND queue = ? AND reservation_token = ?',
            [$id, $this->queue, $reservationToken]
        );

        if ($deleted !== 1) {
            throw new RuntimeException('Cannot acknowledge a job after its reservation expired or changed.');
        }
    }

    /** Re-queue a reserved job after failure, preserving its original queue position. */
    public function release(
        string $id,
        string $reservationToken,
        SerializableJobInterface $job,
        int $attempts,
        int $delaySeconds = 0,
    ): void {
        try {
            $payload = json_encode($job->toArray(), JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Job payload is not JSON-serializable', 0, $e);
        }

        $updated = $this->connection->execute(
            'UPDATE flint_jobs
             SET payload = ?, attempts = ?, available_at = ?, reserved_until = NULL, reservation_token = NULL
             WHERE id = ? AND queue = ? AND reservation_token = ?',
            [
                $payload,
                $attempts,
                $this->timestampAfter(max(0, $delaySeconds)),
                $id,
                $this->queue,
                $reservationToken,
            ]
        );

        if ($updated !== 1) {
            throw new RuntimeException('Cannot release a job after its reservation expired or changed.');
        }
    }

    /** Move a failed job to the dead-letter table and remove its active row atomically. */
    public function fail(
        string $id,
        string $reservationToken,
        SerializableJobInterface $job,
        int $attempts,
        string $error,
    ): void {
        try {
            $payload = json_encode($job->toArray(), JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $payload = '{}';
        }

        $this->connection->transaction(function () use ($id, $reservationToken, $job, $attempts, $payload, $error): void {
            $deleted = $this->connection->execute(
                'DELETE FROM flint_jobs WHERE id = ? AND queue = ? AND reservation_token = ?',
                [$id, $this->queue, $reservationToken]
            );

            if ($deleted !== 1) {
                throw new RuntimeException('Cannot fail a job after its reservation expired or changed.');
            }

            $this->connection->execute(
                'INSERT INTO flint_failed_jobs (id, queue, job_class, payload, attempts, error, failed_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$id, $this->queue, $job::class, $payload, $attempts, mb_substr($error, 0, 2000), $this->timestamp()]
            );
        });
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
                id VARCHAR(191) PRIMARY KEY NOT NULL,
                queue VARCHAR(64) NOT NULL,
                job_class TEXT NOT NULL,
                payload TEXT NOT NULL,
                attempts INTEGER NOT NULL DEFAULT 0,
                available_at VARCHAR(32) NOT NULL,
                created_at VARCHAR(32) NOT NULL,
                reserved_until VARCHAR(32) NULL,
                reservation_token VARCHAR(64) NULL
            )'
        );

        $this->connection->execute(
            'CREATE TABLE IF NOT EXISTS flint_failed_jobs (
                id VARCHAR(191) PRIMARY KEY NOT NULL,
                queue VARCHAR(64) NOT NULL,
                job_class TEXT NOT NULL,
                payload TEXT NOT NULL,
                attempts INTEGER NOT NULL,
                error TEXT NOT NULL,
                failed_at VARCHAR(32) NOT NULL
            )'
        );

        // Upgrade databases created by earlier FlintPHP releases.
        $this->ensureColumn('ALTER TABLE flint_jobs ADD COLUMN reserved_until VARCHAR(32) NULL');
        $this->ensureColumn('ALTER TABLE flint_jobs ADD COLUMN reservation_token VARCHAR(64) NULL');

        $this->ensureIndex('CREATE INDEX flint_jobs_queue_available_at ON flint_jobs (queue, available_at)');
        $this->ensureIndex('CREATE INDEX flint_jobs_reservation ON flint_jobs (queue, reserved_until)');
        $this->ensureIndex('CREATE INDEX flint_failed_jobs_queue ON flint_failed_jobs (queue)');
    }

    private function ensureColumn(string $sql): void
    {
        try {
            $this->connection->execute($sql);
        } catch (QueryException $exception) {
            $previous = $exception->getPrevious();
            $message = strtolower($exception->getMessage() . ' ' . ($previous?->getMessage() ?? ''));
            $code = (string) ($previous?->getCode() ?? '');

            if (
                str_contains($message, 'duplicate column')
                || str_contains($message, 'already exists')
                || $code === '42701'
                || $code === '42S21'
            ) {
                return;
            }

            throw $exception;
        }
    }

    private function ensureIndex(string $sql): void
    {
        try {
            $this->connection->execute($sql);
        } catch (QueryException $exception) {
            if ($this->isDuplicateIndexError($exception)) {
                return;
            }
            throw $exception;
        }
    }

    private function isDuplicateIndexError(QueryException $exception): bool
    {
        $previous = $exception->getPrevious();
        $message = strtolower($exception->getMessage());

        if ($previous instanceof PDOException) {
            $errorInfo = $previous->errorInfo ?? [];
            $sqlState = (string) ($errorInfo[0] ?? $previous->getCode());
            $driverCode = (int) ($errorInfo[1] ?? 0);
            $pdoMessage = strtolower($previous->getMessage());

            if (
                $driverCode === 1061
                || $sqlState === '42P07'
                || $sqlState === '42710'
                || str_contains($pdoMessage, 'duplicate key name')
                || str_contains($pdoMessage, 'already exists')
            ) {
                return true;
            }
        }

        return str_contains($message, 'duplicate key name')
            || str_contains($message, 'already exists')
            || str_contains($message, 'duplicate');
    }

    private function timestamp(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z');
    }

    private function timestampAfter(int $seconds): string
    {
        $timestamp = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify(sprintf('+%d seconds', $seconds));

        if ($timestamp === false) {
            throw new RuntimeException('Could not calculate the queue reservation timestamp.');
        }

        return $timestamp->format('Y-m-d\TH:i:s.u\Z');
    }
}
