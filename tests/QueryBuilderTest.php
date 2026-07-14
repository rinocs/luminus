<?php

use PHPUnit\Framework\TestCase;
use Luminus\Database;
use Luminus\QueryBuilder;

class QueryBuilderTest extends TestCase
{
    private Database $db;
    private \PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->exec('CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT, active INTEGER)');
        $this->pdo->exec("INSERT INTO test (name, active) VALUES ('Alice', 1), ('Bob', 1), ('Charlie', 0)");

        $config = ['driver' => 'mysql', 'database' => 'test'];
        $this->db = $this->getMockBuilder(Database::class)
            ->onlyMethods(['connect', 'query', 'execute', 'insert'])
            ->setConstructorArgs([$config])
            ->getMock();

        $this->db->method('connect')->willReturn($this->pdo);
    }

    public function test_get_returns_all(): void
    {
        $this->db->method('query')->willReturnCallback(
            fn() => $this->pdo->query('SELECT * FROM test')->fetchAll(\PDO::FETCH_ASSOC)
        );

        $rows = (new QueryBuilder($this->db, 'test'))->get();
        $this->assertCount(3, $rows);
    }

    public function test_where_filters(): void
    {
        $this->db->method('query')->willReturnCallback(
            fn(string $sql, array $params) => $this->pdo->prepare($sql)->execute($params)
                ? $this->pdo->query("SELECT * FROM test WHERE active = {$params[0]}")->fetchAll(\PDO::FETCH_ASSOC)
                : []
        );

        $rows = (new QueryBuilder($this->db, 'test'))->where('active', '=', 1)->get();
        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertSame(1, (int)$row['active']);
        }
    }

    public function test_first_returns_single_row(): void
    {
        $this->db->method('query')->willReturnCallback(
            fn(string $sql, array $params) => $this->pdo->prepare($sql)->execute($params)
                ? $this->pdo->query("SELECT * FROM test WHERE name = '{$params[0]}' LIMIT 1")->fetchAll(\PDO::FETCH_ASSOC)
                : []
        );

        $row = (new QueryBuilder($this->db, 'test'))->where('name', '=', 'Alice')->first();
        $this->assertNotNull($row);
        $this->assertSame('Alice', $row['name']);
    }

    public function test_first_returns_null_when_no_match(): void
    {
        $this->db->method('query')->willReturn([]);
        $row = (new QueryBuilder($this->db, 'test'))->where('name', '=', 'Nobody')->first();
        $this->assertNull($row);
    }

    public function test_find_by_id(): void
    {
        $this->db->method('query')->willReturnCallback(
            fn(string $sql, array $params) => $this->pdo->prepare($sql)->execute($params)
                ? $this->pdo->query("SELECT * FROM test WHERE id = {$params[0]} LIMIT 1")->fetchAll(\PDO::FETCH_ASSOC)
                : []
        );

        $row = (new QueryBuilder($this->db, 'test'))->find(1);
        $this->assertNotNull($row);
        $this->assertSame('Alice', $row['name']);
    }

    public function test_count(): void
    {
        $this->db->method('query')->willReturn([['count' => 3]]);
        $count = (new QueryBuilder($this->db, 'test'))->count();
        $this->assertSame(3, $count);
    }

    public function test_order_by(): void
    {
        $this->db->method('query')->willReturnCallback(
            fn() => $this->pdo->query("SELECT * FROM test ORDER BY name DESC")->fetchAll(\PDO::FETCH_ASSOC)
        );

        $rows = (new QueryBuilder($this->db, 'test'))->orderBy('name', 'DESC')->get();
        $this->assertSame('Charlie', $rows[0]['name']);
        $this->assertSame('Bob', $rows[1]['name']);
        $this->assertSame('Alice', $rows[2]['name']);
    }

    public function test_insert(): void
    {
        $this->db->method('insert')->willReturn('4');
        $id = (new QueryBuilder($this->db, 'test'))->insert(['name' => 'Dave', 'active' => 1]);
        $this->assertSame('4', $id);
    }

    public function test_update(): void
    {
        $this->db->method('execute')->willReturn(1);
        $affected = (new QueryBuilder($this->db, 'test'))->where('name', '=', 'Alice')->update(['active' => 0]);
        $this->assertSame(1, $affected);
    }

    public function test_delete(): void
    {
        $this->db->method('execute')->willReturn(1);
        $affected = (new QueryBuilder($this->db, 'test'))->where('name', '=', 'Charlie')->delete();
        $this->assertSame(1, $affected);
    }

    public function test_select_custom_columns(): void
    {
        $capturedSql = '';
        $this->db->method('query')->willReturnCallback(
            function (string $sql) use (&$capturedSql) {
                $capturedSql = $sql;
                return [];
            }
        );

        (new QueryBuilder($this->db, 'test'))->select(['name'])->get();
        $this->assertStringContainsString('SELECT `name`', $capturedSql);
    }

    public function test_where_with_two_args(): void
    {
        $this->db->method('query')->willReturnCallback(
            fn(string $sql, array $params) => $this->pdo->prepare($sql)->execute($params)
                ? $this->pdo->query("SELECT * FROM test WHERE name = '{$params[0]}' LIMIT 1")->fetchAll(\PDO::FETCH_ASSOC)
                : []
        );

        $row = (new QueryBuilder($this->db, 'test'))->where('name', 'Alice')->first();
        $this->assertNotNull($row);
        $this->assertSame('Alice', $row['name']);
    }

    public function test_generated_sql_has_backtick_quoting(): void
    {
        $capturedSql = '';
        $this->db->method('query')->willReturnCallback(
            function (string $sql) use (&$capturedSql) {
                $capturedSql = $sql;
                return [];
            }
        );
        $this->db->method('insert')->willReturnCallback(
            function (string $table, array $data) use (&$capturedSql) {
                $capturedSql = "INSERT INTO `{$table}`";
                return '1';
            }
        );

        $builder = new QueryBuilder($this->db, 'test');

        $builder->select(['id', 'name'])->get();
        $this->assertStringContainsString('FROM `test`', $capturedSql);

        $builder->insert(['name' => 'x']);
        $this->assertStringContainsString('INSERT INTO', $capturedSql);

        $capturedSql = '';
        $this->db->method('execute')->willReturnCallback(
            function (string $sql) use (&$capturedSql) {
                $capturedSql = $sql;
                return 1;
            }
        );
        $builder->where('id', '=', 1)->update(['name' => 'y']);
        $this->assertStringContainsString('UPDATE `test`', $capturedSql);

        $capturedSql = '';
        $builder->where('id', '=', 1)->delete();
        $this->assertStringContainsString('DELETE FROM `test`', $capturedSql);
    }
}
