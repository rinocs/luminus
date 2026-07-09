<?php

use PHPUnit\Framework\TestCase;
use Luminus\Database;
use Luminus\Database\Migrator;

class MigratorTest extends TestCase
{
    private Database $db;
    private \PDO $pdo;
    private string $tempMigrationsPath;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $config = ['driver' => 'sqlite', 'database' => ':memory:'];
        $this->db = new Database($config);
        
        // Mock connection to return our memory DB
        $ref = new \ReflectionClass(Database::class);
        $pdoProp = $ref->getProperty('pdo');
        $pdoProp->setAccessible(true);
        $pdoProp->setValue($this->db, $this->pdo);

        // Setup temporary directory for migrations
        $this->tempMigrationsPath = sys_get_temp_dir() . '/luminus_migrations_' . uniqid();
        mkdir($this->tempMigrationsPath, 0777, true);
    }

    protected function tearDown(): void
    {
        // Clean up temporary migration files
        if (is_dir($this->tempMigrationsPath)) {
            $files = glob($this->tempMigrationsPath . '/*');
            foreach ($files as $file) {
                unlink($file);
            }
            rmdir($this->tempMigrationsPath);
        }
    }

    public function test_ensure_migrations_table_exists(): void
    {
        $migrator = new Migrator($this->db, $this->tempMigrationsPath);
        $migrator->ensureMigrationTableExists();

        $stmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='migrations'");
        $table = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotEmpty($table);
        $this->assertSame('migrations', $table['name']);
    }

    public function test_create_migration_files(): void
    {
        $migrator = new Migrator($this->db, $this->tempMigrationsPath);
        $result = $migrator->create('create_users_table');

        $this->assertFileExists($result['up']);
        $this->assertFileExists($result['down']);
        $this->assertStringContainsString('create_users_table', $result['basename']);

        $upContent = file_get_contents($result['up']);
        $this->assertStringContainsString('create_users_table', $upContent);
    }

    public function test_up_applies_migrations(): void
    {
        $migrator = new Migrator($this->db, $this->tempMigrationsPath);
        
        // Create a migration that creates a table
        $res = $migrator->create('create_dummy_table');
        file_put_contents($res['up'], "CREATE TABLE dummy (id INTEGER PRIMARY KEY, title TEXT)");
        file_put_contents($res['down'], "DROP TABLE dummy");

        // Verify status is pending
        $statusBefore = $migrator->status();
        $this->assertCount(1, $statusBefore);
        $this->assertSame('pending', $statusBefore[0]['status']);

        // Run migrations
        $executed = $migrator->up();
        $this->assertCount(1, $executed);
        $this->assertSame($res['basename'], $executed[0]);

        // Verify table was created
        $stmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='dummy'");
        $dummyTable = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotEmpty($dummyTable);

        // Verify status is applied
        $statusAfter = $migrator->status();
        $this->assertSame('applied', $statusAfter[0]['status']);
        $this->assertSame(1, $statusAfter[0]['batch']);
    }

    public function test_rollback_reverts_migrations(): void
    {
        $migrator = new Migrator($this->db, $this->tempMigrationsPath);
        
        // Create dummy migration
        $res = $migrator->create('create_dummy_table');
        file_put_contents($res['up'], "CREATE TABLE dummy (id INTEGER PRIMARY KEY, title TEXT)");
        file_put_contents($res['down'], "DROP TABLE dummy");

        // Apply
        $migrator->up();

        // Rollback
        $rolledBack = $migrator->rollback();
        $this->assertCount(1, $rolledBack);
        $this->assertSame($res['basename'], $rolledBack[0]);

        // Verify table was dropped
        $stmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='dummy'");
        $dummyTable = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertFalse($dummyTable);

        // Verify status is pending again
        $statusAfter = $migrator->status();
        $this->assertSame('pending', $statusAfter[0]['status']);
    }
}
