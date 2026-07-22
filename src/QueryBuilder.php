<?php

namespace Luminus;

class QueryBuilder
{
    private Database $db;
    private string $table;
    private array $wheres = [];
    private array $params = [];
    private ?string $orderBy = null;
    private ?int $limit = null;
    private ?int $offset = null;
    private array $columns = ['*'];
    private array $eagerLoads = [];

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

    /**
     * Eager load a relation.
     * E.g. $query->with('author', 'author_id', 'users', 'id')
     */
    public function with(string $relation, string $foreignKey, string $relatedTable, string $relatedKey): static
    {
        $this->eagerLoads[$relation] = [
            'foreign_key' => $foreignKey,
            'related_table' => $relatedTable,
            'related_key' => $relatedKey,
        ];
        return $this;
    }

    private const OPERATORS = ['=', '!=', '<>', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE'];

    public function where(string $column, mixed $operator = '=', mixed $value = null): static
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $operator = strtoupper((string) $operator);

        if (!in_array($operator, self::OPERATORS, true)) {
            throw new \InvalidArgumentException("Unsupported operator: {$operator}");
        }

        $column = $this->quoteIdentifier($column);

        if ($value === null) {
            if (!in_array($operator, ['=', '!=', '<>'], true)) {
                throw new \InvalidArgumentException(
                    "NULL value not supported with operator: {$operator}"
                );
            }
            $this->wheres[] = $column . ($operator === '=' ? ' IS NULL' : ' IS NOT NULL');
            return $this;
        }

        $this->wheres[] = "{$column} {$operator} ?";
        $this->params[] = $value;
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $direction = strtoupper($direction);

        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new \InvalidArgumentException("Invalid sort direction: {$direction}");
        }

        $this->orderBy = $this->quoteIdentifier($column) . " {$direction}";
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
        $results = $this->db->query($sql, $this->params);

        if (empty($results) || empty($this->eagerLoads)) {
            return $results;
        }

        foreach ($this->eagerLoads as $relation => $load) {
            $foreignKey = $load['foreign_key'];
            $relatedTable = $load['related_table'];
            $relatedKey = $load['related_key'];

            // Pluck foreign keys
            $ids = array_filter(array_unique(array_column($results, $foreignKey)), fn($val) => $val !== null);

            if (empty($ids)) {
                foreach ($results as &$row) {
                    $row[$relation] = null;
                }
                unset($row);
                continue;
            }

            // Fetch related items
            $placeholders = implode(', ', array_fill(0, count($ids), '?'));
            $quotedRelatedTable = $this->db->quoteIdentifier($relatedTable);
            $quotedRelatedKey = $this->db->quoteIdentifier($relatedKey);

            $relatedSql = "SELECT * FROM {$quotedRelatedTable} WHERE {$quotedRelatedKey} IN ({$placeholders})";
            $relatedRows = $this->db->query($relatedSql, array_values($ids));

            // Map related items by related_key
            $mapped = [];
            foreach ($relatedRows as $rRow) {
                $mapped[$rRow[$relatedKey]] = $rRow;
            }

            // Attach to parents
            foreach ($results as &$row) {
                $fKeyValue = $row[$foreignKey];
                $row[$relation] = $mapped[$fKeyValue] ?? null;
            }
            unset($row);
        }

        return $results;
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
        $columns = implode(', ', array_map(
            fn($col) => $this->quoteIdentifier($col) . ' = ?',
            array_keys($data)
        ));
        $quotedTable = $this->quoteIdentifier($this->table);
        $sql = "UPDATE {$quotedTable} SET {$columns}";

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }

        return $this->db->execute($sql, [...array_values($data), ...$this->params]);
    }

    public function delete(): int
    {
        $quotedTable = $this->quoteIdentifier($this->table);
        $sql = "DELETE FROM {$quotedTable}";

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }

        return $this->db->execute($sql, $this->params);
    }

    public function count(): int
    {
        $previousColumns = $this->columns;
        $this->columns = ['COUNT(*) as count'];
        $result = $this->get();
        $this->columns = $previousColumns;
        return (int) ($result[0]['count'] ?? 0);
    }

    private function quoteIdentifier(string $identifier): string
    {
        return $this->db->quoteIdentifier($identifier);
    }

    private function buildSelect(): string
    {
        $columns = implode(', ', $this->columns);
        $quotedTable = $this->quoteIdentifier($this->table);
        $sql = "SELECT {$columns} FROM {$quotedTable}";

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
