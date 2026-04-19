<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\ORM;

use Coagus\PhpApiBuilder\Helpers\Utils;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionProperty;

class QueryBuilder
{
    private const BOOLEAN_AND = 'AND';
    private const BOOLEAN_OR = 'OR';

    private array $selects = [];
    /** @var list<array{boolean: string, sql: string}> */
    private array $wheres = [];
    private array $bindings = [];
    private array $orderBys = [];
    private ?int $limitValue = null;
    private ?int $offsetValue = null;
    private array $withs = [];
    private ?array $columnAllowlist = null;

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

        $this->selects = array_map(
            fn(string $f) => $this->resolveColumn($f),
            $flat
        );

        return $this;
    }

    public function where(string $field, mixed $operatorOrValue = null, mixed $value = null): static
    {
        $column = $this->resolveColumn($field);

        if ($value === null && $operatorOrValue !== null) {
            $this->wheres[] = ['boolean' => self::BOOLEAN_AND, 'sql' => "{$column} = ?"];
            $this->bindings[] = $operatorOrValue;
        } else {
            $operator = $this->assertOperator((string) $operatorOrValue);
            $this->wheres[] = ['boolean' => self::BOOLEAN_AND, 'sql' => "{$column} {$operator} ?"];
            $this->bindings[] = $value;
        }

        return $this;
    }

    public function orWhere(string $field, mixed $operatorOrValue = null, mixed $value = null): static
    {
        $column = $this->resolveColumn($field);

        if ($value === null && $operatorOrValue !== null) {
            $this->wheres[] = ['boolean' => self::BOOLEAN_OR, 'sql' => "{$column} = ?"];
            $this->bindings[] = $operatorOrValue;
        } else {
            $operator = $this->assertOperator((string) $operatorOrValue);
            $this->wheres[] = ['boolean' => self::BOOLEAN_OR, 'sql' => "{$column} {$operator} ?"];
            $this->bindings[] = $value;
        }

        return $this;
    }

    public function whereIn(string $field, array $values): static
    {
        $column = $this->resolveColumn($field);
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->wheres[] = ['boolean' => self::BOOLEAN_AND, 'sql' => "{$column} IN ({$placeholders})"];
        $this->bindings = array_merge($this->bindings, $values);

        return $this;
    }

    public function whereBetween(string $field, array $range): static
    {
        $column = $this->resolveColumn($field);
        $this->wheres[] = ['boolean' => self::BOOLEAN_AND, 'sql' => "{$column} BETWEEN ? AND ?"];
        $this->bindings[] = $range[0];
        $this->bindings[] = $range[1];

        return $this;
    }

    public function whereNull(string $field): static
    {
        $column = $this->resolveColumn($field);
        $this->wheres[] = ['boolean' => self::BOOLEAN_AND, 'sql' => "{$column} IS NULL"];

        return $this;
    }

    public function whereNotNull(string $field): static
    {
        $column = $this->resolveColumn($field);
        $this->wheres[] = ['boolean' => self::BOOLEAN_AND, 'sql' => "{$column} IS NOT NULL"];

        return $this;
    }

    public function orderBy(string $field, string $direction = 'asc'): static
    {
        $column = $this->resolveColumn($field);
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
        $entities = array_map(fn(array $row) => $entityClass::hydrate($row), $rows);

        if (!empty($this->withs)) {
            $this->eagerLoad($entities);
        }

        return $entities;
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
                'currentPage' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => $totalPages,
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
        $entityClass = $this->entityClass;
        $hasSoftDelete = $entityClass::hasSoftDelete();

        if (empty($this->wheres)) {
            return $hasSoftDelete ? ' WHERE deleted_at IS NULL' : '';
        }

        $userPredicate = $this->joinUserPredicates();

        if ($hasSoftDelete) {
            // Always AND the soft-delete guard with the user's predicate group;
            // parenthesize the user tree so an OR inside can't broaden the result
            // to include soft-deleted rows.
            return ' WHERE deleted_at IS NULL AND (' . $userPredicate . ')';
        }

        return ' WHERE ' . $userPredicate;
    }

    private function joinUserPredicates(): string
    {
        $parts = [];
        foreach ($this->wheres as $index => $clause) {
            if ($index === 0) {
                // The first clause drops its boolean prefix — it's the root.
                $parts[] = $clause['sql'];
                continue;
            }
            $parts[] = $clause['boolean'] . ' ' . $clause['sql'];
        }

        return implode(' ', $parts);
    }

    private function getTable(): string
    {
        $entityClass = $this->entityClass;

        return $entityClass::getTableName();
    }

    private function resolveColumn(string $field): string
    {
        $column = Utils::camelToSnake($field);

        if (!$this->isValidIdentifier($column)) {
            throw new InvalidArgumentException(
                "Invalid identifier [{$field}] — only alphanumeric and underscore characters are allowed."
            );
        }

        return $column;
    }

    private function isValidIdentifier(string $identifier): bool
    {
        return (bool) preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier);
    }

    /**
     * Returns the list of DB column names backing this entity's public properties.
     * `deleted_at` is always included since soft-delete is a library-level convention.
     *
     * Used by `APIDB::applySorting` / `applyFields` to reject identifiers
     * coming from `?sort=` / `?fields=` that don't map to a real column.
     *
     * @return list<string>
     */
    public function getColumnAllowlist(): array
    {
        if ($this->columnAllowlist !== null) {
            return $this->columnAllowlist;
        }

        $ref = new ReflectionClass($this->entityClass);
        $columns = [];
        foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $columns[] = Utils::camelToSnake($prop->getName());
        }

        if (!in_array('deleted_at', $columns, true)) {
            $columns[] = 'deleted_at';
        }

        $this->columnAllowlist = $columns;

        return $this->columnAllowlist;
    }

    private function assertOperator(string $operator): string
    {
        $allowed = ['=', '!=', '<>', '<', '<=', '>', '>=', 'LIKE', 'NOT LIKE'];
        $normalized = strtoupper($operator);

        if (!in_array($operator, $allowed, true) && !in_array($normalized, $allowed, true)) {
            throw new InvalidArgumentException("Unsupported operator [{$operator}].");
        }

        return in_array($normalized, $allowed, true) ? $normalized : $operator;
    }

    private function eagerLoad(array $entities): void
    {
        if (empty($entities)) {
            return;
        }

        $entityClass = $this->entityClass;

        foreach ($this->withs as $relationPath) {
            $parts = explode('.', $relationPath);
            $relationName = $parts[0];

            $meta = $entityClass::getRelationMeta($relationName);
            if ($meta === null) {
                continue;
            }

            match ($meta['type']) {
                'belongsTo' => $this->eagerLoadBelongsTo($entities, $relationName, $meta),
                'hasMany' => $this->eagerLoadHasMany($entities, $relationName, $meta),
                'belongsToMany' => $this->eagerLoadBelongsToMany($entities, $relationName, $meta),
            };

            // Handle nested eager loading
            if (count($parts) > 1) {
                $nestedRelation = implode('.', array_slice($parts, 1));
                $relatedEntities = [];
                foreach ($entities as $entity) {
                    $related = $entity->{$relationName};
                    if (is_array($related)) {
                        $relatedEntities = array_merge($relatedEntities, $related);
                    } elseif ($related !== null) {
                        $relatedEntities[] = $related;
                    }
                }
                if (!empty($relatedEntities)) {
                    $relatedClass = $meta['entity'];
                    $nestedQb = new self($relatedClass);
                    $nestedQb->withs = [$nestedRelation];
                    $nestedQb->eagerLoad($relatedEntities);
                }
            }
        }
    }

    private function eagerLoadBelongsTo(array $entities, string $relationName, array $meta): void
    {
        $relatedClass = $meta['entity'];
        $fk = $meta['foreignKey'] ?? Utils::camelToSnake($relationName) . '_id';
        $fkCamel = Utils::snakeToCamel($fk);

        $ids = array_unique(array_filter(array_map(
            fn($e) => $e->{$fkCamel} ?? null,
            $entities
        )));

        if (empty($ids)) {
            return;
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $relatedTable = $relatedClass::getTableName();
        $relatedPk = $relatedClass::getPrimaryKeyField();
        $relatedPkCol = Utils::camelToSnake($relatedPk);

        $rows = Connection::getInstance()->query(
            "SELECT * FROM {$relatedTable} WHERE {$relatedPkCol} IN ({$placeholders})",
            array_values($ids)
        );

        $indexed = [];
        foreach ($rows as $row) {
            $related = $relatedClass::hydrate($row);
            $indexed[$related->{$relatedPk}] = $related;
        }

        foreach ($entities as $entity) {
            $fkValue = $entity->{$fkCamel} ?? null;
            $entity->setRelation($relationName, $indexed[$fkValue] ?? null);
        }
    }

    private function eagerLoadHasMany(array $entities, string $relationName, array $meta): void
    {
        $relatedClass = $meta['entity'];
        $entityClass = $this->entityClass;
        $pk = $entityClass::getPrimaryKeyField();
        $fk = $meta['foreignKey'] ?? Utils::camelToSnake((new \ReflectionClass($entityClass))->getShortName()) . '_id';

        $ids = array_map(fn($e) => $e->{$pk}, $entities);

        if (empty($ids)) {
            return;
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $relatedTable = $relatedClass::getTableName();

        $rows = Connection::getInstance()->query(
            "SELECT * FROM {$relatedTable} WHERE {$fk} IN ({$placeholders})",
            $ids
        );

        $grouped = [];
        $fkCamel = Utils::snakeToCamel($fk);
        foreach ($rows as $row) {
            $related = $relatedClass::hydrate($row);
            $parentId = $related->{$fkCamel};
            $grouped[$parentId][] = $related;
        }

        foreach ($entities as $entity) {
            $entity->setRelation($relationName, $grouped[$entity->{$pk}] ?? []);
        }
    }

    private function eagerLoadBelongsToMany(array $entities, string $relationName, array $meta): void
    {
        $relatedClass = $meta['entity'];
        $pivotTable = $meta['pivotTable'];
        $foreignPivotKey = $meta['foreignPivotKey'];
        $relatedPivotKey = $meta['relatedPivotKey'];
        $entityClass = $this->entityClass;
        $pk = $entityClass::getPrimaryKeyField();
        $relatedTable = $relatedClass::getTableName();

        $ids = array_map(fn($e) => $e->{$pk}, $entities);

        if (empty($ids)) {
            return;
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));

        $rows = Connection::getInstance()->query(
            "SELECT r.*, p.{$foreignPivotKey} as _pivot_fk FROM {$relatedTable} r INNER JOIN {$pivotTable} p ON p.{$relatedPivotKey} = r.id WHERE p.{$foreignPivotKey} IN ({$placeholders})",
            $ids
        );

        $grouped = [];
        foreach ($rows as $row) {
            $parentId = $row['_pivot_fk'];
            unset($row['_pivot_fk']);
            $grouped[$parentId][] = $relatedClass::hydrate($row);
        }

        foreach ($entities as $entity) {
            $entity->setRelation($relationName, $grouped[$entity->{$pk}] ?? []);
        }
    }
}
