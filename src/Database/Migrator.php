<?php

namespace Luminus\Database;

use Luminus\Database;

class Migrator
{
    private Database $db;
    private string $migrationsPath;
    private string $tableName = 'migrations';

    public function __construct(Database $db, ?string $migrationsPath = null)
    {
        $this->db = $db;
        $this->migrationsPath = $migrationsPath ?? realpath(__DIR__ . '/../../database/migrations') ?: (__DIR__ . '/../../database/migrations');
    }

    /**
     * Ensure the migrations tracking table exists in the database.
     */
    public function ensureMigrationTableExists(): void
    {
        $pdo = $this->db->connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS `{$this->tableName}` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `migration` VARCHAR(255) NOT NULL,
                `batch` INTEGER NOT NULL,
                `applied_at` DATETIME DEFAULT CURRENT_TIMESTAMP
            )";
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS `{$this->tableName}` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `migration` VARCHAR(255) NOT NULL,
                `batch` INT NOT NULL,
                `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        }

        $this->db->execute($sql);
    }

    /**
     * Retrieve all applied migrations from the database.
     */
    public function getAppliedMigrations(): array
    {
        $this->ensureMigrationTableExists();
        return $this->db->query("SELECT * FROM `{$this->tableName}` ORDER BY `id` ASC");
    }

    /**
     * Get names of all applied migrations.
     */
    public function getAppliedMigrationNames(): array
    {
        $applied = $this->getAppliedMigrations();
        return array_column($applied, 'migration');
    }

    /**
     * Get list of migration files in the filesystem, sorted alphabetically/chronologically.
     */
    public function getMigrationFiles(): array
    {
        if (!is_dir($this->migrationsPath)) {
            mkdir($this->migrationsPath, 0775, true);
        }

        $files = glob($this->migrationsPath . '/*.up.sql');
        if ($files === false) {
            return [];
        }

        $migrations = [];
        foreach ($files as $file) {
            $filename = basename($file, '.up.sql');
            $migrations[$filename] = [
                'up' => $file,
                'down' => str_replace('.up.sql', '.down.sql', $file),
            ];
        }

        ksort($migrations);
        return $migrations;
    }

    /**
     * Run all pending migrations.
     */
    public function up(): array
    {
        $this->ensureMigrationTableExists();
        $applied = $this->getAppliedMigrationNames();
        $allMigrations = $this->getMigrationFiles();

        $pending = [];
        foreach ($allMigrations as $name => $paths) {
            if (!in_array($name, $applied)) {
                $pending[$name] = $paths;
            }
        }

        if (empty($pending)) {
            return [];
        }

        // Determine next batch number
        $batches = $this->db->query("SELECT MAX(`batch`) as max_batch FROM `{$this->tableName}`");
        $nextBatch = ((int)($batches[0]['max_batch'] ?? 0)) + 1;

        $executed = [];
        foreach ($pending as $name => $paths) {
            $sql = file_get_contents($paths['up']);
            if ($sql === false) {
                throw new \RuntimeException("Could not read migration file: {$paths['up']}");
            }

            $sql = trim($sql);
            if ($sql !== '') {
                $this->db->connect()->exec($sql);
            }

            // Log migration
            $this->db->insert($this->tableName, [
                'migration' => $name,
                'batch' => $nextBatch,
            ]);

            $executed[] = $name;
        }

        return $executed;
    }

    /**
     * Rollback the last batch of migrations.
     */
    public function rollback(): array
    {
        $this->ensureMigrationTableExists();

        // Determine highest batch number
        $lastBatchResult = $this->db->query("SELECT MAX(`batch`) as max_batch FROM `{$this->tableName}`");
        $lastBatch = $lastBatchResult[0]['max_batch'] ?? null;

        if ($lastBatch === null) {
            return [];
        }

        // Fetch applied migrations of the last batch in reverse order
        $migrations = $this->db->query(
            "SELECT * FROM `{$this->tableName}` WHERE `batch` = ? ORDER BY `id` DESC",
            [$lastBatch]
        );

        $rolledBack = [];
        foreach ($migrations as $migration) {
            $name = $migration['migration'];
            $downFile = $this->migrationsPath . '/' . $name . '.down.sql';

            if (!file_exists($downFile)) {
                throw new \RuntimeException("Rollback file not found: {$downFile}");
            }

            $sql = file_get_contents($downFile);
            if ($sql === false) {
                throw new \RuntimeException("Could not read rollback file: {$downFile}");
            }

            $sql = trim($sql);
            if ($sql !== '') {
                $this->db->connect()->exec($sql);
            }

            // Delete migration record
            $this->db->execute("DELETE FROM `{$this->tableName}` WHERE `id` = ?", [$migration['id']]);
            $rolledBack[] = $name;
        }

        return $rolledBack;
    }

    /**
     * Get the status of all migrations (both applied and pending).
     */
    public function status(): array
    {
        $this->ensureMigrationTableExists();
        $applied = [];
        foreach ($this->getAppliedMigrations() as $row) {
            $applied[$row['migration']] = $row;
        }

        $allMigrations = $this->getMigrationFiles();
        $status = [];

        foreach ($allMigrations as $name => $paths) {
            if (isset($applied[$name])) {
                $status[] = [
                    'migration' => $name,
                    'status' => 'applied',
                    'batch' => $applied[$name]['batch'],
                    'applied_at' => $applied[$name]['applied_at'],
                ];
            } else {
                $status[] = [
                    'migration' => $name,
                    'status' => 'pending',
                    'batch' => null,
                    'applied_at' => null,
                ];
            }
        }

        return $status;
    }

    /**
     * Create a new migration file pair (up and down SQL).
     */
    public function create(string $name): array
    {
        if (!is_dir($this->migrationsPath)) {
            mkdir($this->migrationsPath, 0775, true);
        }

        $timestamp = date('YmdHis');
        $slug = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($name));
        $basename = "{$timestamp}_{$slug}";

        $upFile = "{$this->migrationsPath}/{$basename}.up.sql";
        $downFile = "{$this->migrationsPath}/{$basename}.down.sql";

        file_put_contents($upFile, "-- Migration: {$name}\n-- Write your UP queries here\n");
        file_put_contents($downFile, "-- Migration: {$name}\n-- Write your DOWN queries here\n");

        return [
            'up' => $upFile,
            'down' => $downFile,
            'basename' => $basename,
        ];
    }
}
