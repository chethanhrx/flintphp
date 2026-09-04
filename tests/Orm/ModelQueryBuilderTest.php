<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Orm;

use FlintPHP\Framework\Database\ConnectionFactory;
use FlintPHP\Framework\Database\ConnectionInterface;
use FlintPHP\Framework\Database\Exception\QueryException;
use FlintPHP\Framework\Orm\Exception\ModelNotFoundException;
use FlintPHP\Framework\Orm\Model;
use FlintPHP\Framework\Orm\OrmManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class QueryTestUser extends Model
{
    protected string $table = 'test_users';
    
    public int $id;
    public string $name;
    public string $email;
    public int $age;
}

#[CoversClass(\FlintPHP\Framework\Orm\ModelQueryBuilder::class)]
#[RequiresPhpExtension('pdo_sqlite')]
final class ModelQueryBuilderTest extends TestCase
{
    private ConnectionInterface $connection;
    private OrmManager $orm;

    protected function setUp(): void
    {
        $this->connection = ConnectionFactory::make([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $this->connection->execute(
            'CREATE TABLE test_users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, age INTEGER)'
        );

        $this->connection->execute('INSERT INTO test_users (name, email, age) VALUES (?, ?, ?)', ['A', 'a@a.com', 10]);
        $this->connection->execute('INSERT INTO test_users (name, email, age) VALUES (?, ?, ?)', ['B', 'b@b.com', 20]);
        $this->connection->execute('INSERT INTO test_users (name, email, age) VALUES (?, ?, ?)', ['C', 'c@c.com', 30]);

        $this->orm = new OrmManager($this->connection);
    }

    #[Test]
    public function it_can_get_all_matching_records(): void
    {
        $users = $this->orm->query(QueryTestUser::class)->where('age', 20)->get();

        $this->assertCount(1, $users);
        $this->assertInstanceOf(QueryTestUser::class, $users[0]);
        $this->assertSame('B', $users[0]->name);
    }

    #[Test]
    public function it_can_chain_where_clauses(): void
    {
        $user = $this->orm->query(QueryTestUser::class)
            ->where('name', 'C')
            ->where('age', 30)
            ->first();

        $this->assertNotNull($user);
        $this->assertSame('c@c.com', $user->email);
    }

    #[Test]
    public function it_can_count_records(): void
    {
        $count = $this->orm->query(QueryTestUser::class)->count();
        $this->assertSame(3, $count);

        $countFiltered = $this->orm->query(QueryTestUser::class)->where('age', 20)->count();
        $this->assertSame(1, $countFiltered);
    }

    #[Test]
    public function it_can_check_existence(): void
    {
        $exists = $this->orm->query(QueryTestUser::class)->where('name', 'A')->exists();
        $this->assertTrue($exists);

        $notExists = $this->orm->query(QueryTestUser::class)->where('name', 'Z')->exists();
        $this->assertFalse($notExists);
    }

    #[Test]
    public function it_throws_query_exception_on_invalid_column_identifier(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Invalid SQL identifier');

        $this->orm->query(QueryTestUser::class)->where('invalid; DROP TABLE test_users', 'foo')->get();
    }

    #[Test]
    public function it_throws_model_not_found_on_first_or_fail(): void
    {
        $this->expectException(ModelNotFoundException::class);
        
        $this->orm->query(QueryTestUser::class)->where('id', 999)->firstOrFail();
    }
}
