<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Orm;

use FlintPHP\Framework\Database\ConnectionFactory;
use FlintPHP\Framework\Database\ConnectionInterface;
use FlintPHP\Framework\Orm\Exception\MassAssignmentException;
use FlintPHP\Framework\Orm\Exception\ModelNotFoundException;
use FlintPHP\Framework\Orm\Model;
use FlintPHP\Framework\Orm\OrmManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TestUser extends Model
{
    protected string $table = 'test_users';
    protected array $fillable = ['name', 'email', 'age'];

    public int $id;
    public string $name;
    public string $email;
    public int $age;
    public bool $is_admin = false;
}

class UnfillableModel extends Model
{
    protected string $table = 'test_users';
}

#[CoversClass(OrmManager::class)]
#[RequiresPhpExtension('pdo_sqlite')]
final class OrmManagerTest extends TestCase
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
            'CREATE TABLE test_users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, age INTEGER, is_admin BOOLEAN)'
        );

        $this->orm = new OrmManager($this->connection);
    }

    #[Test]
    public function it_can_insert_a_new_model_and_hydrate_id(): void
    {
        $user = new TestUser();
        $user->name = 'Alice';
        $user->email = 'alice@example.com';
        $user->age = 30;

        $result = $this->orm->save($user);

        $this->assertTrue($result);
        $this->assertIsInt($user->id);
        $this->assertGreaterThan(0, $user->id);

        $count = $this->connection->fetchColumn('SELECT COUNT(*) FROM test_users');
        $this->assertEquals(1, $count);
    }

    #[Test]
    public function it_can_update_an_existing_model(): void
    {
        $this->connection->execute('INSERT INTO test_users (name, email, age, is_admin) VALUES (?, ?, ?, ?)', ['Bob', 'bob@example.com', 40, false]);
        $id = (int) $this->connection->pdo()->lastInsertId();

        $user = $this->orm->find(TestUser::class, $id);
        $this->assertNotNull($user);

        $user->name = 'Bob Updated';
        $this->orm->save($user);

        $updatedName = $this->connection->fetchColumn('SELECT name FROM test_users WHERE id = ?', [$id]);
        $this->assertSame('Bob Updated', $updatedName);
    }

    #[Test]
    public function it_can_find_or_fail(): void
    {
        $this->connection->execute('INSERT INTO test_users (name, email, age, is_admin) VALUES (?, ?, ?, ?)', ['Charlie', 'c@c.com', 20, false]);
        $id = (int) $this->connection->pdo()->lastInsertId();

        $user = $this->orm->findOrFail(TestUser::class, $id);
        $this->assertSame('Charlie', $user->name);

        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('No query results for model [' . TestUser::class . '] with id [999].');
        
        $this->orm->findOrFail(TestUser::class, 999);
    }

    #[Test]
    public function it_can_delete_a_model(): void
    {
        $this->connection->execute('INSERT INTO test_users (name, email, age, is_admin) VALUES (?, ?, ?, ?)', ['Dave', 'd@d.com', 50, false]);
        $id = (int) $this->connection->pdo()->lastInsertId();

        $user = $this->orm->find(TestUser::class, $id);
        $this->assertNotNull($user);

        $result = $this->orm->delete($user);
        $this->assertTrue($result);

        $count = $this->connection->fetchColumn('SELECT COUNT(*) FROM test_users');
        $this->assertEquals(0, $count);
    }

    #[Test]
    public function it_safely_fills_models_using_fillable_array(): void
    {
        $user = new TestUser();
        
        $this->orm->fill($user, [
            'name' => 'Eve',
            'email' => 'eve@example.com',
            'age' => 25,
            'is_admin' => true, // Malicious attempt
            'non_existent' => 'foo',
        ]);

        $this->assertSame('Eve', $user->name);
        $this->assertSame('eve@example.com', $user->email);
        $this->assertSame(25, $user->age);
        
        // is_admin should NOT be true because it's not in $fillable
        $this->assertFalse($user->is_admin);
    }

    #[Test]
    public function it_throws_mass_assignment_exception_if_fillable_is_empty(): void
    {
        $model = new UnfillableModel();

        $this->expectException(MassAssignmentException::class);
        $this->expectExceptionMessage('does not define any fillable attributes');

        $this->orm->fill($model, ['name' => 'Test']);
    }
}
