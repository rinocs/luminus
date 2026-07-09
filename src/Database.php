<?php

namespace Luminus;

class Database
{
    private ?\PDO $pdo = null;
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function connect(): \PDO
    {
        if ($this->pdo === null) {
            if (empty($this->config['database'])) {
                throw new \RuntimeException('Database name is not configured');
            }

            if (($this->config['driver'] ?? 'mysql') === 'sqlite') {
                $dsn = 'sqlite:' . $this->config['database'];
            } else {
                $dsn = sprintf(
                    '%s:host=%s;port=%s;dbname=%s;charset=%s',
                    $this->config['driver'] ?? 'mysql',
                    $this->config['host'] ?? '127.0.0.1',
                    $this->config['port'] ?? '3306',
                    $this->config['database'],
                    $this->config['charset'] ?? 'utf8mb4'
                );
            }

            $this->pdo = new \PDO(
                $dsn,
                $this->config['username'] ?? null,
                $this->config['password'] ?? null,
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        }

        return $this->pdo;
    }

    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function insert(string $table, array $data): string
    {
        $columns = implode(', ', array_map(fn($col) => "`{$col}`", array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $this->execute(
            "INSERT INTO `{$table}` ({$columns}) VALUES ({$placeholders})",
            array_values($data)
        );

        return $this->connect()->lastInsertId();
    }

    public function table(string $table): QueryBuilder
    {
        return new QueryBuilder($this, $table);
    }

    public function beginTransaction(): void
    {
        $this->connect()->beginTransaction();
    }

    public function commit(): void
    {
        $this->connect()->commit();
    }

    public function rollback(): void
    {
        $this->connect()->rollBack();
    }
}
