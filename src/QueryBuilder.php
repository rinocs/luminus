<?php

namespace Luminus;

class QueryBuilder
{
    private readonly Database $db;
    private readonly string $table;
    private array $wheres = [];
    private array $params = [];
    private ?string $orderBy = null;
    private ?int $limit = null;
    private ?int $offset = null;
    private array $columns = ['*'];

    public function __construct(Database $db, string $table)
    {
        $this->db = $db;
        $this->table = $table;
    }

    public function select(array $columns = ['*']): static
    {
        $this->columns = $columns;
        return $this;
    }

    public function where(string $column, string $operator, mixed $value = null): static
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = $this->db->quoteIdentifier($column) . " {$operator} ?";
        $this->params[] = $value;
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $this->orderBy = $this->db->quoteIdentifier($column) . " {$direction}";
        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): static
    {
        $this->offset = $offset;
        return $this;
    }

    public function get(): array
    {
        $sql = $this->buildSelect();

        return $this->db->query($sql, $this->params);
    }

    public function first(): ?array
    {
        $this->limit(1);
        $results = $this->get();

        return $results[0] ?? null;
    }

    public function find(mixed $id, string $column = 'id'): ?array
    {
        return $this->where($column, '=', $id)->first();
    }

    public function insert(array $data): string
    {
        return $this->db->insert($this->table, $data);
    }

    public function update(array $data): int
    {
        $columns = implode(', ', array_map(fn($col) => $this->db->quoteIdentifier($col) . " = ?", array_keys($data)));
        $sql = "UPDATE " . $this->db->quoteIdentifier($this->table) . " SET {$columns}";

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }

        return $this->db->execute($sql, [...array_values($data), ...$this->params]);
    }

    public function delete(): int
    {
        $sql = "DELETE FROM " . $this->db->quoteIdentifier($this->table);

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }

        return $this->db->execute($sql, $this->params);
    }

    public function count(): int
    {
        $this->columns = ['COUNT(*) as count'];
        $result = $this->get();
        return (int) ($result[0]['count'] ?? 0);
    }

    private function buildSelect(): string
    {
        $columns = $this->columns === ['*'] ? '*' : implode(', ', array_map(fn($col) => $col === '*' ? '*' : $this->db->quoteIdentifier($col), $this->columns));
        $sql = "SELECT {$columns} FROM " . $this->db->quoteIdentifier($this->table);

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }

        if ($this->orderBy) {
            $sql .= " ORDER BY {$this->orderBy}";
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }

        if ($this->offset !== null) {
            $sql .= " OFFSET {$this->offset}";
        }

        return $sql;
    }
}
