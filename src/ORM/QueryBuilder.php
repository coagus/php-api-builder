<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\ORM;

use Coagus\PhpApiBuilder\Helpers\Utils;

class QueryBuilder
{
    private array $selects = [];
    private array $wheres = [];
    private array $bindings = [];
    private array $orderBys = [];
    private ?int $limitValue = null;
    private ?int $offsetValue = null;
    private array $withs = [];

    public function __construct(
        private readonly string $entityClass
    ) {}

    public function select(string|array ...$fields): static
    {
        $flat = [];
        foreach ($fields as $f) {
            if (is_array($f)) {
                $flat = array_merge($flat, $f);
            } else {
                $flat[] = $f;
            }
        }
        $this->selects = array_map(fn(string $f) => Utils::camelToSnake($f), $flat);

        return $this;
    }

    public function where(string $field, mixed $operatorOrValue = null, mixed $value = null): static
    {
        $column = Utils::camelToSnake($field);

        if ($value === null && $operatorOrValue !== null) {
            $this->wheres[] = "{$column} = ?";
            $this->bindings[] = $operatorOrValue;
        } else {
            $operator = $operatorOrValue;
            $this->wheres[] = "{$column} {$operator} ?";
            $this->bindings[] = $value;
        }

        return $this;
    }

    public function orWhere(string $field, mixed $operatorOrValue = null, mixed $value = null): static
    {
        $column = Utils::camelToSnake($field);

        if ($value === null && $operatorOrValue !== null) {
            $this->wheres[] = "OR {$column} = ?";
            $this->bindings[] = $operatorOrValue;
        } else {
            $operator = $operatorOrValue;
            $this->wheres[] = "OR {$column} {$operator} ?";
            $this->bindings[] = $value;
        }

        return $this;
    }

    public function whereIn(string $field, array $values): static
    {
        $column = Utils::camelToSnake($field);
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->wheres[] = "{$column} IN ({$placeholders})";
        $this->bindings = array_merge($this->bindings, $values);

        return $this;
    }

    public function whereBetween(string $field, array $range): static
    {
        $column = Utils::camelToSnake($field);
        $this->wheres[] = "{$column} BETWEEN ? AND ?";
        $this->bindings[] = $range[0];
        $this->bindings[] = $range[1];

        return $this;
    }

    public function whereNull(string $field): static
    {
        $column = Utils::camelToSnake($field);
        $this->wheres[] = "{$column} IS NULL";

        return $this;
    }

    public function whereNotNull(string $field): static
    {
        $column = Utils::camelToSnake($field);
        $this->wheres[] = "{$column} IS NOT NULL";

        return $this;
    }

    public function orderBy(string $field, string $direction = 'asc'): static
    {
        $column = Utils::camelToSnake($field);
        $dir = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orderBys[] = "{$column} {$dir}";

        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limitValue = $limit;

        return $this;
    }

    public function offset(int $offset): static
    {
        $this->offsetValue = $offset;

        return $this;
    }

    public function with(string ...$relations): static
    {
        $this->withs = array_merge($this->withs, $relations);

        return $this;
    }

    public function get(): array
    {
        $sql = $this->buildSelectSql();
        $rows = Connection::getInstance()->query($sql, $this->bindings);

        $entityClass = $this->entityClass;

        return array_map(fn(array $row) => $entityClass::hydrate($row), $rows);
    }

    public function first(): ?object
    {
        $this->limitValue = 1;
        $results = $this->get();

        return $results[0] ?? null;
    }

    public function count(): int
    {
        $table = $this->getTable();
        $sql = "SELECT COUNT(*) as count FROM {$table}";
        $sql .= $this->buildWhereSql();

        $rows = Connection::getInstance()->query($sql, $this->bindings);

        return (int) ($rows[0]['count'] ?? 0);
    }

    public function paginate(int $page = 1, int $perPage = 15): array
    {
        $total = (clone $this)->count();
        $totalPages = (int) ceil($total / $perPage);

        $this->limitValue = $perPage;
        $this->offsetValue = ($page - 1) * $perPage;
        $data = $this->get();

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
        ];
    }

    public function toSql(): string
    {
        return $this->buildSelectSql();
    }

    public function getBindings(): array
    {
        return $this->bindings;
    }

    public function __call(string $name, array $arguments): static
    {
        $scopeMethod = 'scope' . ucfirst($name);
        $entityClass = $this->entityClass;

        if (method_exists($entityClass, $scopeMethod)) {
            return $entityClass::$scopeMethod($this, ...$arguments);
        }

        throw new \BadMethodCallException("Scope [{$name}] does not exist on {$entityClass}.");
    }

    private function buildSelectSql(): string
    {
        $table = $this->getTable();
        $columns = empty($this->selects) ? '*' : implode(', ', $this->selects);
        $sql = "SELECT {$columns} FROM {$table}";

        $sql .= $this->buildWhereSql();

        if (!empty($this->orderBys)) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderBys);
        }

        if ($this->limitValue !== null) {
            $sql .= " LIMIT {$this->limitValue}";
            if ($this->offsetValue !== null) {
                $sql .= " OFFSET {$this->offsetValue}";
            }
        }

        return $sql;
    }

    private function buildWhereSql(): string
    {
        if (empty($this->wheres)) {
            $entityClass = $this->entityClass;
            if ($entityClass::hasSoftDelete()) {
                return ' WHERE deleted_at IS NULL';
            }
            return '';
        }

        $clauses = [];
        $hasSoftDelete = $this->entityClass::hasSoftDelete();

        if ($hasSoftDelete) {
            $clauses[] = 'deleted_at IS NULL';
        }

        foreach ($this->wheres as $i => $clause) {
            if (str_starts_with($clause, 'OR ')) {
                $clauses[] = $clause;
            } else {
                if (!empty($clauses)) {
                    $clauses[] = "AND {$clause}";
                } else {
                    $clauses[] = $clause;
                }
            }
        }

        return ' WHERE ' . implode(' ', $clauses);
    }

    private function getTable(): string
    {
        $entityClass = $this->entityClass;

        return $entityClass::getTableName();
    }
}
