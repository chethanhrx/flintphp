<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Orm;

use FlintPHP\Framework\Database\Exception\QueryException;
use FlintPHP\Framework\Orm\SqlIdentifierQuoter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SqlIdentifierQuoter::class)]
final class SqlIdentifierQuoterTest extends TestCase
{
    #[Test]
    public function it_quotes_identifiers_for_mysql(): void
    {
        $this->assertSame('`users`', SqlIdentifierQuoter::quote('users', 'mysql'));
    }

    #[Test]
    public function it_quotes_identifiers_for_sqlite_and_postgresql(): void
    {
        $this->assertSame('"users"', SqlIdentifierQuoter::quote('users', 'sqlite'));
        $this->assertSame('"users"', SqlIdentifierQuoter::quote('users', 'pgsql'));
    }

    #[Test]
    public function it_rejects_invalid_identifiers(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Invalid SQL identifier');

        SqlIdentifierQuoter::quote('users; DROP TABLE users', 'mysql');
    }

    #[Test]
    public function it_rejects_unknown_drivers(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Unsupported SQL identifier quoting');

        SqlIdentifierQuoter::quote('users', 'oci');
    }
}
