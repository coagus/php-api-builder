<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\ORM;

use Coagus\PhpApiBuilder\Attributes\BelongsTo;
use Coagus\PhpApiBuilder\Attributes\BelongsToMany;
use Coagus\PhpApiBuilder\Attributes\HasMany;
use Coagus\PhpApiBuilder\Attributes\PrimaryKey;
use Coagus\PhpApiBuilder\Attributes\SoftDelete;
use Coagus\PhpApiBuilder\Attributes\Table;
use Coagus\PhpApiBuilder\Helpers\Utils;
use Coagus\PhpApiBuilder\Validation\Attributes\Hidden;
use Coagus\PhpApiBuilder\Validation\Validator;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;

abstract class Entity
{
    private static array $metadataCache = [];
    private array $loadedRelations = [];

    public static function getTableName(): string
    {
        $meta = static::getMetadata();
        return $meta['table'];
    }

    public static function getPrimaryKeyField(): string
    {
        $meta = static::getMetadata();
        return $meta['primaryKey'];
    }

    public static function hasSoftDelete(): bool
    {
        $meta = static::getMetadata();
        return $meta['softDelete'];
    }

    public static function find(int|string $id): ?static
    {
        $table = static::getTableName();
        $pk = static::getPrimaryKeyField();
        $pkColumn = Utils::camelToSnake($pk);

        $sql = "SELECT * FROM {$table} WHERE {$pkColumn} = ?";
        if (static::hasSoftDelete()) {
            $sql .= ' AND deleted_at IS NULL';
        }
        $sql .= ' LIMIT 1';

        $rows = Connection::getInstance()->query($sql, [$id]);

        if (empty($rows)) {
            return null;
        }

        return static::hydrate($rows[0]);
    }

    public static function all(): array
    {
        $table = static::getTableName();
        $sql = "SELECT * FROM {$table}";

        if (static::hasSoftDelete()) {
            $sql .= ' WHERE deleted_at IS NULL';
        }

        $rows = Connection::getInstance()->query($sql);

        return array_map(fn(array $row) => static::hydrate($row), $rows);
    }

    public static function query(): QueryBuilder
    {
        return new QueryBuilder(static::class);
    }

    public static function __callStatic(string $name, array $arguments): mixed
    {
        $scopeMethod = 'scope' . ucfirst($name);

        if (method_exists(static::class, $scopeMethod)) {
            $query = static::query();
            return static::$scopeMethod($query, ...$arguments);
        }

        throw new \BadMethodCallException("Method [{$name}] does not exist on " . static::class . '.');
    }

    public function save(): static
    {
        $errors = Validator::validate($this);
        if ($errors !== null) {
            throw new RuntimeException(json_encode($errors));
        }

        $pk = static::getPrimaryKeyField();
        $pkValue = $this->{$pk} ?? null;

        if ($pkValue === null || $pkValue === 0) {
            $this->beforeCreate();
            $this->insert();
            $this->afterCreate();
        } else {
            $this->beforeUpdate();
            $this->update();
            $this->afterUpdate();
        }

        return $this;
    }

    public function delete(): void
    {
        $this->beforeDelete();

        $table = static::getTableName();
        $pk = static::getPrimaryKeyField();
        $pkColumn = Utils::camelToSnake($pk);
        $pkValue = $this->{$pk};

        if (static::hasSoftDelete()) {
            Connection::getInstance()->execute(
                "UPDATE {$table} SET deleted_at = datetime('now') WHERE {$pkColumn} = ?",
                [$pkValue]
            );
        } else {
            Connection::getInstance()->execute(
                "DELETE FROM {$table} WHERE {$pkColumn} = ?",
                [$pkValue]
            );
        }

        $this->afterDelete();
    }

    public function fill(array $data): static
    {
        $ref = new ReflectionClass($this);
        foreach ($data as $key => $value) {
            $camelKey = Utils::snakeToCamel($key);
            if ($ref->hasProperty($camelKey)) {
                $prop = $ref->getProperty($camelKey);
                if ($prop->isPublic()) {
                    $this->{$camelKey} = $this->castValue($prop, $value);
                }
            }
        }

        return $this;
    }

    public function toArray(): array
    {
        $ref = new ReflectionClass($this);
        $result = [];

        foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            if (!empty($prop->getAttributes(Hidden::class))) {
                continue;
            }

            $name = $prop->getName();
            if (!$prop->isInitialized($this)) {
                continue;
            }

            $value = $prop->getValue($this);

            // Include loaded relations as nested arrays
            if (!empty($prop->getAttributes(BelongsTo::class))
                || !empty($prop->getAttributes(HasMany::class))
                || !empty($prop->getAttributes(BelongsToMany::class))) {
                if (in_array($name, $this->loadedRelations, true)) {
                    if ($value instanceof Entity) {
                        $result[Utils::camelToSnake($name)] = $value->toArray();
                    } elseif (is_array($value)) {
                        $result[Utils::camelToSnake($name)] = array_map(fn(Entity $e) => $e->toArray(), $value);
                    }
                }
                continue;
            }

            $result[Utils::camelToSnake($name)] = $value;
        }

        return $result;
    }

    public function loadRelation(string $relationName): void
    {
        $ref = new ReflectionClass($this);
        if (!$ref->hasProperty($relationName)) {
            return;
        }

        $prop = $ref->getProperty($relationName);

        $belongsToAttrs = $prop->getAttributes(BelongsTo::class);
        if (!empty($belongsToAttrs)) {
            $attr = $belongsToAttrs[0]->newInstance();
            $relatedClass = $attr->entity;
            $fk = $attr->foreignKey ?? Utils::camelToSnake($relationName) . '_id';
            $fkCamel = Utils::snakeToCamel($fk);
            if (isset($this->{$fkCamel})) {
                $this->{$relationName} = $relatedClass::find($this->{$fkCamel});
            }
            $this->loadedRelations[] = $relationName;
            return;
        }

        $hasManyAttrs = $prop->getAttributes(HasMany::class);
        if (!empty($hasManyAttrs)) {
            $attr = $hasManyAttrs[0]->newInstance();
            $relatedClass = $attr->entity;
            $fk = $attr->foreignKey ?? Utils::camelToSnake((new ReflectionClass(static::class))->getShortName()) . '_id';
            $fkCamel = Utils::snakeToCamel($fk);
            $this->{$relationName} = $relatedClass::query()->where($fkCamel, $this->{static::getPrimaryKeyField()})->get();
            $this->loadedRelations[] = $relationName;
            return;
        }

        $belongsToManyAttrs = $prop->getAttributes(BelongsToMany::class);
        if (!empty($belongsToManyAttrs)) {
            $attr = $belongsToManyAttrs[0]->newInstance();
            $relatedClass = $attr->entity;
            $pivotTable = $attr->pivotTable;
            $foreignPivotKey = $attr->foreignPivotKey;
            $relatedPivotKey = $attr->relatedPivotKey;
            $relatedTable = $relatedClass::getTableName();
            $pkValue = $this->{static::getPrimaryKeyField()};

            $sql = "SELECT r.* FROM {$relatedTable} r INNER JOIN {$pivotTable} p ON p.{$relatedPivotKey} = r.id WHERE p.{$foreignPivotKey} = ?";
            $rows = Connection::getInstance()->query($sql, [$pkValue]);
            $this->{$relationName} = array_map(fn(array $row) => $relatedClass::hydrate($row), $rows);
            $this->loadedRelations[] = $relationName;
        }
    }

    public function setRelation(string $name, mixed $value): void
    {
        $this->{$name} = $value;
        $this->loadedRelations[] = $name;
    }

    public static function getRelationMeta(string $propertyName): ?array
    {
        $ref = new ReflectionClass(static::class);
        if (!$ref->hasProperty($propertyName)) {
            return null;
        }

        $prop = $ref->getProperty($propertyName);

        $belongsTo = $prop->getAttributes(BelongsTo::class);
        if (!empty($belongsTo)) {
            $attr = $belongsTo[0]->newInstance();
            return ['type' => 'belongsTo', 'entity' => $attr->entity, 'foreignKey' => $attr->foreignKey];
        }

        $hasMany = $prop->getAttributes(HasMany::class);
        if (!empty($hasMany)) {
            $attr = $hasMany[0]->newInstance();
            return ['type' => 'hasMany', 'entity' => $attr->entity, 'foreignKey' => $attr->foreignKey];
        }

        $belongsToMany = $prop->getAttributes(BelongsToMany::class);
        if (!empty($belongsToMany)) {
            $attr = $belongsToMany[0]->newInstance();
            return [
                'type' => 'belongsToMany',
                'entity' => $attr->entity,
                'pivotTable' => $attr->pivotTable,
                'foreignPivotKey' => $attr->foreignPivotKey,
                'relatedPivotKey' => $attr->relatedPivotKey,
            ];
        }

        return null;
    }

    protected function beforeCreate(): void {}
    protected function afterCreate(): void {}
    protected function beforeUpdate(): void {}
    protected function afterUpdate(): void {}
    protected function beforeDelete(): void {}
    protected function afterDelete(): void {}

    public static function hydrate(array $row): static
    {
        $entity = new static();
        $ref = new ReflectionClass($entity);

        foreach ($row as $column => $value) {
            $camelName = Utils::snakeToCamel($column);
            if ($ref->hasProperty($camelName)) {
                $prop = $ref->getProperty($camelName);
                if ($prop->isPublic()) {
                    if ($value === null && $prop->getType() && !$prop->getType()->allowsNull()) {
                        continue;
                    }
                    $castedValue = self::castValueStatic($prop, $value);

                    if ($prop->isProtectedSet() || $prop->isPrivateSet()) {
                        $prop->setRawValueWithoutLazyInitialization($entity, $castedValue);
                    } else {
                        $entity->{$camelName} = $castedValue;
                    }
                }
            }
        }

        return $entity;
    }

    private function insert(): void
    {
        $table = static::getTableName();
        $pk = static::getPrimaryKeyField();
        $data = $this->getColumnsAndValues($pk);

        if (empty($data)) {
            return;
        }

        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        Connection::getInstance()->execute(
            "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})",
            array_values($data)
        );

        $id = Connection::getInstance()->lastInsertId();
        if ($id !== false && $id !== '0') {
            $ref = new ReflectionClass($this);
            $prop = $ref->getProperty($pk);
            $castedId = (int) $id;

            if ($prop->isProtectedSet() || $prop->isPrivateSet()) {
                $prop->setRawValueWithoutLazyInitialization($this, $castedId);
            } else {
                $this->{$pk} = $castedId;
            }
        }
    }

    private function update(): void
    {
        $table = static::getTableName();
        $pk = static::getPrimaryKeyField();
        $pkColumn = Utils::camelToSnake($pk);
        $data = $this->getColumnsAndValues($pk);

        if (empty($data)) {
            return;
        }

        $sets = implode(', ', array_map(fn(string $col) => "{$col} = ?", array_keys($data)));
        $values = array_values($data);
        $values[] = $this->{$pk};

        Connection::getInstance()->execute(
            "UPDATE {$table} SET {$sets} WHERE {$pkColumn} = ?",
            $values
        );
    }

    private function getColumnsAndValues(string $excludePk): array
    {
        $ref = new ReflectionClass($this);
        $data = [];

        foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $name = $prop->getName();
            if ($name === $excludePk) {
                continue;
            }
            if (!$prop->isInitialized($this)) {
                continue;
            }
            // Skip relation properties
            if (!empty($prop->getAttributes(BelongsTo::class))
                || !empty($prop->getAttributes(HasMany::class))
                || !empty($prop->getAttributes(BelongsToMany::class))) {
                continue;
            }

            $value = $prop->getValue($this);
            $column = Utils::camelToSnake($name);

            if (is_bool($value)) {
                $value = $value ? 1 : 0;
            }

            $data[$column] = $value;
        }

        return $data;
    }

    private function castValue(ReflectionProperty $prop, mixed $value): mixed
    {
        return self::castValueStatic($prop, $value);
    }

    private static function castValueStatic(ReflectionProperty $prop, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $type = $prop->getType();
        if ($type === null) {
            return $value;
        }

        $typeName = $type->getName();

        return match ($typeName) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => (bool) $value,
            'string' => (string) $value,
            default => $value,
        };
    }

    private static function getMetadata(): array
    {
        $class = static::class;

        if (isset(self::$metadataCache[$class])) {
            return self::$metadataCache[$class];
        }

        $ref = new ReflectionClass($class);

        $tableAttrs = $ref->getAttributes(Table::class);
        if (!empty($tableAttrs)) {
            $tableName = $tableAttrs[0]->newInstance()->name;
        } else {
            $tableName = Utils::tableize($class);
        }

        $primaryKey = 'id';
        foreach ($ref->getProperties() as $prop) {
            if (!empty($prop->getAttributes(PrimaryKey::class))) {
                $primaryKey = $prop->getName();
                break;
            }
        }

        $softDelete = !empty($ref->getAttributes(SoftDelete::class));

        self::$metadataCache[$class] = [
            'table' => $tableName,
            'primaryKey' => $primaryKey,
            'softDelete' => $softDelete,
        ];

        return self::$metadataCache[$class];
    }

    public static function clearMetadataCache(): void
    {
        self::$metadataCache = [];
    }
}
