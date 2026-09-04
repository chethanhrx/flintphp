<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Orm;

use FlintPHP\Framework\Orm\Exception\OrmException;
use FlintPHP\Framework\Orm\Internal\ModelHydrator;
use FlintPHP\Framework\Orm\Model;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class HydratorTestModel extends Model
{
    protected string $table = 'test_table';
    protected string $primaryKey = 'id';
    protected array $fillable = [];

    public int $id;
    public string $name;
    public ?string $nullableString;
    public bool $isActive;

    protected string $protectedVar = 'safe';
    private string $privateVar = 'safe';
    public static string $staticVar = 'safe';
    
    // No constructor by default, but let's test one with constructor requirements in another model
}

class HydratorConstructorModel extends Model
{
    public string $name;
    
    public function __construct(string $requiredArg)
    {
        $this->name = clone $requiredArg; // Just doing something that crashes if string is invalid
    }
}

#[CoversClass(ModelHydrator::class)]
final class ModelHydratorTest extends TestCase
{
    private ModelHydrator $hydrator;

    protected function setUp(): void
    {
        $this->hydrator = new ModelHydrator();
    }

    #[Test]
    public function it_hydrates_only_public_non_static_properties(): void
    {
        $data = [
            'id' => 1,
            'name' => 'Alice',
            'table' => 'malicious_table',
            'primaryKey' => 'malicious_pk',
            'fillable' => ['malicious'],
            'protectedVar' => 'hacked',
            'privateVar' => 'hacked',
            'staticVar' => 'hacked',
            'unknown_column' => 'ignored',
        ];

        $model = $this->hydrator->hydrate(HydratorTestModel::class, $data);

        $this->assertSame(1, $model->id);
        $this->assertSame('Alice', $model->name);
        
        // Ensure protected internal metadata is NOT overwritten
        $this->assertSame('test_table', $model->getTable());
        $this->assertSame('id', $model->getPrimaryKey());
        $this->assertSame([], $model->getFillable());
        
        // Static var is unaffected
        $this->assertSame('safe', HydratorTestModel::$staticVar);
    }

    #[Test]
    public function it_extracts_only_initialized_public_properties(): void
    {
        $model = new HydratorTestModel();
        $model->id = 5;
        // name is left uninitialized
        $model->nullableString = null;
        $model->isActive = false;

        $data = $this->hydrator->extract($model);

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('nullableString', $data);
        $this->assertArrayHasKey('isActive', $data);
        $this->assertArrayNotHasKey('name', $data); // Uninitialized
        $this->assertArrayNotHasKey('table', $data); // Protected
        $this->assertArrayNotHasKey('primaryKey', $data); // Protected
        
        $this->assertSame(5, $data['id']);
        $this->assertNull($data['nullableString']);
        $this->assertFalse($data['isActive']);
    }

    #[Test]
    public function it_coerces_types_via_php_typed_properties(): void
    {
        $data = [
            'id' => '123', // Numeric string
            'name' => 'Bob',
            'isActive' => 1, // Integer boolean
        ];

        $model = $this->hydrator->hydrate(HydratorTestModel::class, $data);

        $this->assertSame(123, $model->id); // Coerced to int by PHP 8
        $this->assertSame('Bob', $model->name);
        $this->assertSame(true, $model->isActive); // Coerced to bool by PHP 8
    }

    #[Test]
    public function it_throws_type_error_on_incompatible_hydration(): void
    {
        $data = [
            'id' => 'invalid-int',
        ];

        $this->expectException(\TypeError::class);

        $this->hydrator->hydrate(HydratorTestModel::class, $data);
    }

    #[Test]
    public function it_bypasses_constructors_safely_during_hydration(): void
    {
        $data = [
            'name' => 'Hydrated Without Constructor',
        ];

        // Constructor requires arguments and does logic, but hydration bypasses it
        // This proves it's safe to load from DB without triggering initialization side-effects
        $model = $this->hydrator->hydrate(HydratorConstructorModel::class, $data);

        $this->assertSame('Hydrated Without Constructor', $model->name);
    }
}
