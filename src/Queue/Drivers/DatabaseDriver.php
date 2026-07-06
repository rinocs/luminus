<?php

namespace Luminus\Queue\Drivers;

use Luminus\Database;
use Luminus\Queue\Contracts\QueueDriverInterface;

class DatabaseDriver implements QueueDriverInterface
{
    private Database $db;
    private string $table;
    private string $failedTable;

    public function __construct(Database $db, array $config)
    {
        $this->db = $db;
        $this->table = $config['table'] ?? 'jobs';
        $this->failedTable = $config['failed_table'] ?? 'failed_jobs';
    }

    public function push(string $queue, string $payload, int $delay = 0): mixed
    {
        $availableAt = time() + $delay;

        return $this->db->insert($this->table, [
            'queue' => $queue,
            'payload' => $payload,
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => $availableAt,
            'created_at' => time()
        ]);
    }

    public function pop(string $queue): ?array
    {
        $this->db->beginTransaction();

        try {
            $now = time();
            
            // Assuming MySQL, using FOR UPDATE. For SQLite, FOR UPDATE might not work in all versions, 
            // but we'll use a standard approach for a real DB driver.
            $sql = "SELECT * FROM `{$this->table}` 
                    WHERE queue = ? AND reserved_at IS NULL AND available_at <= ? 
                    ORDER BY id ASC LIMIT 1 FOR UPDATE";
            
            $jobs = $this->db->query($sql, [$queue, $now]);

            if (empty($jobs)) {
                $this->db->commit();
                return null;
            }

            $job = $jobs[0];

            $reservedAt = time();
            $attempts = $job['attempts'] + 1;

            $this->db->execute(
                "UPDATE `{$this->table}` SET reserved_at = ?, attempts = ? WHERE id = ?",
                [$reservedAt, $attempts, $job['id']]
            );

            $this->db->commit();

            return [
                'id' => $job['id'],
                'payload' => $job['payload'],
                'attempts' => $attempts,
                'meta' => $job
            ];
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function delete(string $queue, mixed $id): void
    {
        $this->db->execute("DELETE FROM `{$this->table}` WHERE id = ?", [$id]);
    }

    public function release(string $queue, mixed $id, int $delay = 0): void
    {
        $availableAt = time() + $delay;

        $this->db->execute(
            "UPDATE `{$this->table}` SET reserved_at = NULL, available_at = ? WHERE id = ?",
            [$availableAt, $id]
        );
    }

    public function fail(string $queue, string $payload, string $exception): void
    {
        $this->db->insert($this->failedTable, [
            'queue' => $queue,
            'payload' => $payload,
            'exception' => $exception,
            'failed_at' => time()
        ]);
    }
}
