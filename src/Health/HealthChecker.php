<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Health;

use FlintPHP\Framework\Database\ConnectionInterface;
use FlintPHP\Framework\Http\Response;
use Throwable;

/**
 * Builds a health payload. Wire to GET /health yourself.
 */
final class HealthChecker
{
    /**
     * @param array<string, callable(): bool> $checks name => callable returning true if healthy
     */
    public function __construct(
        private readonly array $checks = [],
    ) {
    }

    public static function withDatabase(ConnectionInterface $db): self
    {
        return new self([
            'database' => static function () use ($db): bool {
                $db->fetchColumn('SELECT 1');

                return true;
            },
        ]);
    }

    /**
     * @return array{status: string, checks: array<string, string>}
     */
    public function check(): array
    {
        $results = [];
        $ok = true;

        foreach ($this->checks as $name => $check) {
            try {
                $pass = (bool) $check();
                $results[$name] = $pass ? 'ok' : 'fail';
                if (!$pass) {
                    $ok = false;
                }
            } catch (Throwable) {
                $results[$name] = 'fail';
                $ok = false;
            }
        }

        return [
            'status' => $ok ? 'ok' : 'degraded',
            'checks' => $results,
        ];
    }

    public function toResponse(): Response
    {
        $payload = $this->check();
        $status = $payload['status'] === 'ok' ? 200 : 503;

        return Response::json($payload, $status);
    }
}
