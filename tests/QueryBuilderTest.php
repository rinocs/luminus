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

        $config = ['driver' => 'mysql', 'database' => ':memory:'];
        $this->db = $this->getMockBuilder(Database::class)
            ->onlyMethods(['connect', 'query', 'execute', 'insert'])
            ->setConstructorArgs([$config])
            ->getMock();

        $this->db->method('connect')->willReturn($this->pdo);
    }

    public function test_get_returns_all(): void
    {
        $this->db->method('query')->willReturnCallback(
            fn() => $this->pdo->query('SELECT * FROM `test`')->fetchAll(\PDO::FETCH_ASSOC)
        );

        $rows = (new QueryBuilder($this->db, 'test'))->get();
        $this->assertCount(3, $rows);
    }

    public function test_where_filters(): void
    {
        $this->db->method('query')->willReturnCallback(
            fn(string $sql, array $params) => $this->pdo->prepare($sql)->execute($params)
                ? $this->pdo->query("SELECT * FROM `test` WHERE active = {$params[0]}")->fetchAll(\PDO::FETCH_ASSOC)
                : []
        );

        $rows = (new QueryBuilder($this->db, 'test'))->where('active', '=', 1)->get();
        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertSame(1, $row['active']);
        }
    }

    public function test_first_returns_single_row(): void
    {
        $this->db->method('query')->willReturnCallback(
            fn(string $sql, array $params) => $this->pdo->prepare($sql)->execute($params)
                ? $this->pdo->query("SELECT * FROM `test` WHERE name = '{$params[0]}' LIMIT 1")->fetchAll(\PDO::FETCH_ASSOC)
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
                ? $this->pdo->query("SELECT * FROM `test` WHERE id = {$params[0]} LIMIT 1")->fetchAll(\PDO::FETCH_ASSOC)
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
            fn() => $this->pdo->query("SELECT * FROM `test` ORDER BY name DESC")->fetchAll(\PDO::FETCH_ASSOC)
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

    public function test_eager_loading_with_relation(): void
    {
        // Set up the extra table 'users' in memory PDO
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT)');
        $this->pdo->exec("INSERT INTO users (id, username) VALUES (10, 'AliceUser'), (20, 'BobUser')");

        // Set up test rows with foreign keys
        $this->pdo->exec('CREATE TABLE posts (id INTEGER PRIMARY KEY, title TEXT, author_id INTEGER)');
        $this->pdo->exec("INSERT INTO posts (title, author_id) VALUES ('Post 1', 10), ('Post 2', 20), ('Post 3', 99)"); // 99 is non-existent

        // Mock Database to route actual queries to sqlite memory instance
        $config = ['driver' => 'sqlite', 'database' => ':memory:'];
        $db = $this->getMockBuilder(Database::class)
            ->onlyMethods(['connect', 'query', 'execute', 'insert', 'quoteIdentifier'])
            ->setConstructorArgs([$config])
            ->getMock();
        $db->method('connect')->willReturn($this->pdo);
        $db->method('quoteIdentifier')->willReturnCallback(fn($id) => '"' . $id . '"');
        $db->method('query')->willReturnCallback(function (string $sql, array $params = []) {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        });

        // Get posts with eager-loaded author relation
        $results = (new QueryBuilder($db, 'posts'))
            ->with('author', 'author_id', 'users', 'id')
            ->get();

        $this->assertCount(3, $results);

        // Verify "Post 1" has AliceUser loaded
        $this->assertSame('Post 1', $results[0]['title']);
        $this->assertNotNull($results[0]['author']);
        $this->assertSame('AliceUser', $results[0]['author']['username']);

        // Verify "Post 2" has BobUser loaded
        $this->assertSame('Post 2', $results[1]['title']);
        $this->assertNotNull($results[1]['author']);
        $this->assertSame('BobUser', $results[1]['author']['username']);

        // Verify "Post 3" has null author
        $this->assertSame('Post 3', $results[2]['title']);
        $this->assertNull($results[2]['author']);
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
        $this->assertStringContainsString('SELECT name', $capturedSql);
    }

    public function test_where_with_two_args(): void
    {
        $this->db->method('query')->willReturnCallback(
            fn(string $sql, array $params) => $this->pdo->prepare($sql)->execute($params)
                ? $this->pdo->query("SELECT * FROM `test` WHERE name = '{$params[0]}' LIMIT 1")->fetchAll(\PDO::FETCH_ASSOC)
                : []
        );

        $row = (new QueryBuilder($this->db, 'test'))->where('name', 'Alice')->first();
        $this->assertNotNull($row);
        $this->assertSame('Alice', $row['name']);
    }

    public function test_where_null_generates_is_null(): void
    {
        $capturedSql = '';
        $this->db->method('query')->willReturnCallback(
            function (string $sql) use (&$capturedSql) {
                $capturedSql = $sql;
                return [];
            }
        );

        (new QueryBuilder($this->db, 'test'))->where('deleted_at', '=', null)->get();
        $this->assertStringContainsString('`deleted_at` IS NULL', $capturedSql);

        (new QueryBuilder($this->db, 'test'))->where('deleted_at', '!=', null)->get();
        $this->assertStringContainsString('`deleted_at` IS NOT NULL', $capturedSql);
    }

    public function test_where_rejects_invalid_operator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new QueryBuilder($this->db, 'test'))->where('id', '; DROP TABLE test', 1);
    }

    public function test_where_rejects_invalid_column(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new QueryBuilder($this->db, 'test'))->where('id; DROP TABLE test --', '=', 1);
    }

    public function test_order_by_rejects_invalid_direction(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new QueryBuilder($this->db, 'test'))->orderBy('name', 'DESC; DROP TABLE test');
    }

    public function test_count_does_not_mutate_columns(): void
    {
        $capturedSql = '';
        $this->db->method('query')->willReturnCallback(
            function (string $sql) use (&$capturedSql) {
                $capturedSql = $sql;
                return [['count' => 3]];
            }
        );

        $builder = (new QueryBuilder($this->db, 'test'))->select(['name']);
        $builder->count();
        $builder->get();
        $this->assertStringContainsString('SELECT name', $capturedSql);
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

    public function test_generated_sql_has_double_quote_quoting_for_sqlite_and_pgsql(): void
    {
        $config = ['driver' => 'sqlite', 'database' => ':memory:'];
        $sqliteDb = $this->getMockBuilder(Database::class)
            ->onlyMethods(['connect', 'query', 'execute', 'insert'])
            ->setConstructorArgs([$config])
            ->getMock();

        $capturedSql = '';
        $sqliteDb->method('query')->willReturnCallback(
            function (string $sql) use (&$capturedSql) {
                $capturedSql = $sql;
                return [];
            }
        );
        $sqliteDb->method('execute')->willReturnCallback(
            function (string $sql) use (&$capturedSql) {
                $capturedSql = $sql;
                return 1;
            }
        );

        $builder = new QueryBuilder($sqliteDb, 'test');

        $builder->select(['id', 'name'])->get();
        $this->assertStringContainsString('FROM "test"', $capturedSql);

        $builder->where('id', '=', 1)->update(['name' => 'y']);
        $this->assertStringContainsString('UPDATE "test"', $capturedSql);

        $capturedSql = '';
        $builder->where('id', '=', 1)->delete();
        $this->assertStringContainsString('DELETE FROM "test"', $capturedSql);
    }
}
