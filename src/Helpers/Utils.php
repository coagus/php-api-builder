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
