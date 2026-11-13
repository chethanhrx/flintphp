<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Integration;

use FlintPHP\Framework\Database\ConnectionFactory;
use FlintPHP\Framework\Database\ConnectionInterface;
use FlintPHP\Framework\Orm\Model;
use FlintPHP\Framework\Orm\OrmManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Minimal model for cross-engine ORM proof (MySQL 8.4 / PostgreSQL 17).
 */
final class IntegrationProduct extends Model
{
    protected string $table = 'flintphp_orm_products';

    protected string $primaryKey = 'id';

    /** @var list<string> */
    protected array $fillable = ['name', 'price'];

    public ?int $id = null;

    public string $name = '';

    public int $price = 0;
}

final class OrmEngineIntegrationTest extends TestCase
{
    private ConnectionInterface $db;

    private OrmManager $orm;

    protected function setUp(): void
    {
        $driver = getenv('FLINTPHP_TEST_DB_DRIVER');

        if ($driver === false || $driver === '') {
            self::markTestSkipped(
                'Set FLINTPHP_TEST_DB_DRIVER to mysql or pgsql to run database integration tests.'
            );
        }

        if (!in_array($driver, ['mysql', 'pgsql'], true)) {
            self::fail('FLINTPHP_TEST_DB_DRIVER must be mysql or pgsql.');
        }

        $config = [
            'driver' => $driver,
            'host' => getenv('FLINTPHP_TEST_DB_HOST') ?: '127.0.0.1',
            'port' => getenv('FLINTPHP_TEST_DB_PORT')
                ?: ($driver === 'mysql' ? 3307 : 5433),
            'database' => getenv('FLINTPHP_TEST_DB_NAME') ?: 'flintphp_test',
            'username' => getenv('FLINTPHP_TEST_DB_USER') ?: 'flintphp',
            'password' => getenv('FLINTPHP_TEST_DB_PASSWORD') ?: 'flintphp_test',
        ];

        if ($driver === 'mysql') {
            $config['charset'] = 'utf8mb4';
        }

        $this->db = ConnectionFactory::make($config);
        $this->assertSame(1, (int) $this->db->fetchColumn('SELECT 1'));

        $this->db->execute('DROP TABLE IF EXISTS flintphp_orm_products');

        if ($driver === 'mysql') {
            $this->db->execute(
                'CREATE TABLE flintphp_orm_products (
                    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(191) NOT NULL,
                    price INT NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
        } else {
            $this->db->execute(
                'CREATE TABLE flintphp_orm_products (
                    id SERIAL PRIMARY KEY,
                    name VARCHAR(191) NOT NULL,
                    price INTEGER NOT NULL
                )'
            );
        }

        $this->orm = new OrmManager($this->db);
    }

    #[Test]
    public function inserts_finds_updates_and_deletes_a_model(): void
    {
        $product = new IntegrationProduct();
        $product->name = 'Flint Widget';
        $product->price = 1500;

        self::assertTrue($this->orm->save($product));
        self::assertNotNull($product->id);
        self::assertGreaterThan(0, $product->id);

        $found = $this->orm->find(IntegrationProduct::class, $product->id);
        self::assertInstanceOf(IntegrationProduct::class, $found);
        self::assertSame('Flint Widget', $found->name);
        self::assertSame(1500, (int) $found->price);

        $found->name = 'Flint Widget Pro';
        $found->price = 1999;
        self::assertTrue($this->orm->save($found));

        $updated = $this->orm->findOrFail(IntegrationProduct::class, $product->id);
        self::assertSame('Flint Widget Pro', $updated->name);
        self::assertSame(1999, (int) $updated->price);

        $matched = $this->orm->query(IntegrationProduct::class)
            ->where('name', 'Flint Widget Pro')
            ->where('price', '>', 1000)
            ->orderBy('id', 'DESC')
            ->limit(5)
            ->get();

        self::assertCount(1, $matched);
        self::assertSame($product->id, $matched[0]->id);

        self::assertTrue($this->orm->delete($updated));
        self::assertNull($this->orm->find(IntegrationProduct::class, $product->id));
    }

    #[Test]
    public function supports_quoted_identifiers_on_reserved_style_names(): void
    {
        // Table/column names are validated; quoting must not break simple selects.
        $count = $this->orm->query(IntegrationProduct::class)->count();
        self::assertSame(0, $count);

        $product = new IntegrationProduct();
        $product->name = 'alpha';
        $product->price = 1;
        $this->orm->save($product);

        self::assertTrue($this->orm->query(IntegrationProduct::class)->where('name', 'alpha')->exists());
        self::assertSame(1, $this->orm->query(IntegrationProduct::class)->count());
    }
}
