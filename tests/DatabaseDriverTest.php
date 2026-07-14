<?php

use PHPUnit\Framework\TestCase;
use Luminus\Database;

class DatabaseDriverTest extends TestCase
{
    public function test_sqlite_quoting(): void
    {
        $config = ['driver' => 'sqlite', 'database' => ':memory:'];
        $db = new Database($config);

        $this->assertSame('"test"', $db->quoteIdentifier('test'));
    }

    public function test_mysql_quoting(): void
    {
        $config = ['driver' => 'mysql', 'database' => 'test'];
        $db = new Database($config);

        $this->assertSame('`test`', $db->quoteIdentifier('test'));
    }
}
