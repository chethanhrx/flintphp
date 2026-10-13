<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Orm;

use FlintPHP\Framework\Database\Exception\QueryException;

/**
 * Quotes validated SQL identifiers for the PDO drivers supported by FlintPHP.
 *
 * Values must still be bound as parameters; quoting is only for identifiers.
 */
final class SqlIdentifierQuoter
{
    public static function quote(string $identifier, string $driver): string
    {
        if (preg_match('/\A[a-zA-Z0-9_]+\z/', $identifier) !== 1) {
            throw new QueryException(sprintf('Invalid SQL identifier: %s', $identifier));
        }

        $quote = match ($driver) {
            'mysql' => '`',
            'sqlite', 'pgsql' => '"',
            default => throw new QueryException(sprintf(
                'Unsupported SQL identifier quoting for PDO driver: %s',
                $driver
            )),
        };

        return $quote . $identifier . $quote;
    }
}
