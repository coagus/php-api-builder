<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\Helpers;

class Utils
{
    public static function camelToSnake(string $input): string
    {
        return strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($input)));
    }

    public static function snakeToCamel(string $input): string
    {
        return lcfirst(str_replace('_', '', ucwords($input, '_')));
    }

    /**
     * Infers the DB column name for a BelongsTo foreign key from a PHP property name.
     *
     * Idempotent on the `_id` suffix: if the snake_cased property already ends
     * in `_id` (e.g. `category_id`, `userId`), it is returned unchanged.
     * Otherwise `_id` is appended.
     *
     * Examples:
     *   categoryId → category_id
     *   category_id → category_id (no double suffix)
     *   user → user_id
     */
    public static function foreignKeyColumn(string $propertyName): string
    {
        $snake = self::camelToSnake($propertyName);

        return str_ends_with($snake, '_id') ? $snake : $snake . '_id';
    }

    public static function tableize(string $className): string
    {
        $shortName = (new \ReflectionClass($className))->getShortName();
        $snake = self::camelToSnake($shortName);

        if (str_ends_with($snake, 'y') && !str_ends_with($snake, 'ay') && !str_ends_with($snake, 'ey') && !str_ends_with($snake, 'oy') && !str_ends_with($snake, 'uy')) {
            return substr($snake, 0, -1) . 'ies';
        }

        if (str_ends_with($snake, 's') || str_ends_with($snake, 'x') || str_ends_with($snake, 'sh') || str_ends_with($snake, 'ch')) {
            return $snake . 'es';
        }

        return $snake . 's';
    }
}
