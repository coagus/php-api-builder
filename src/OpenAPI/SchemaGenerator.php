<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\OpenAPI;

use Coagus\PhpApiBuilder\Attributes\BelongsTo;
use Coagus\PhpApiBuilder\Attributes\Description;
use Coagus\PhpApiBuilder\Attributes\Example;
use Coagus\PhpApiBuilder\Attributes\HasMany;
use Coagus\PhpApiBuilder\Attributes\PrimaryKey;
use Coagus\PhpApiBuilder\Helpers\Utils;
use Coagus\PhpApiBuilder\Validation\Attributes\DefaultValue;
use Coagus\PhpApiBuilder\Validation\Attributes\Email;
use Coagus\PhpApiBuilder\Validation\Attributes\Hidden;
use Coagus\PhpApiBuilder\Validation\Attributes\In;
use Coagus\PhpApiBuilder\Validation\Attributes\IsReadOnly;
use Coagus\PhpApiBuilder\Validation\Attributes\Max;
use Coagus\PhpApiBuilder\Validation\Attributes\MaxLength;
use Coagus\PhpApiBuilder\Validation\Attributes\Min;
use Coagus\PhpApiBuilder\Validation\Attributes\MinLength;
use Coagus\PhpApiBuilder\Validation\Attributes\Pattern;
use Coagus\PhpApiBuilder\Validation\Attributes\Required;
use ReflectionClass;
use ReflectionProperty;

class SchemaGenerator
{
    public static function generate(string $entityClass): array
    {
        $ref = new ReflectionClass($entityClass);
        $properties = [];
        $required = [];

        foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            if (!empty($prop->getAttributes(Hidden::class))) {
                continue;
            }

            // Skip relation collection properties
            if (!empty($prop->getAttributes(HasMany::class))) {
                continue;
            }

            $name = $prop->getName();
            $schema = self::propertyToSchema($prop);

            if (!empty($prop->getAttributes(Required::class))) {
                $required[] = $name;
            }

            $properties[$name] = $schema;
        }

        $result = [
            'type' => 'object',
            'properties' => $properties,
        ];

        if (!empty($required)) {
            $result['required'] = $required;
        }

        return $result;
    }

    public static function generateForCreate(string $entityClass): array
    {
        $ref = new ReflectionClass($entityClass);
        $properties = [];
        $required = [];

        foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            // Skip primary key for create
            if (!empty($prop->getAttributes(PrimaryKey::class))) {
                continue;
            }

            // Skip collection relations (HasMany)
            if (!empty($prop->getAttributes(HasMany::class))) {
                continue;
            }

            // Skip read-only fields (auto-generated: created_at, updated_at, etc.)
            if (!empty($prop->getAttributes(IsReadOnly::class))) {
                continue;
            }

            // Skip Hidden fields
            if (!empty($prop->getAttributes(Hidden::class))) {
                continue;
            }

            // BelongsTo FK fields (userId, postId) SHOULD be included

            $name = $prop->getName();
            $schema = self::propertyToSchema($prop, false);

            if (!empty($prop->getAttributes(Required::class))) {
                $required[] = $name;
            }

            $properties[$name] = $schema;
        }

        $result = [
            'type' => 'object',
            'properties' => $properties,
        ];

        if (!empty($required)) {
            $result['required'] = $required;
        }

        return $result;
    }

    private static function propertyToSchema(ReflectionProperty $prop, bool $includeReadOnly = true): array
    {
        $schema = self::typeToSchema($prop);

        if ($includeReadOnly && !empty($prop->getAttributes(PrimaryKey::class))) {
            $schema['readOnly'] = true;
        }

        if (!empty($prop->getAttributes(Email::class))) {
            $schema['format'] = 'email';
        }

        $maxLength = $prop->getAttributes(MaxLength::class);
        if (!empty($maxLength)) {
            $schema['maxLength'] = $maxLength[0]->newInstance()->max;
        }

        $minLength = $prop->getAttributes(MinLength::class);
        if (!empty($minLength)) {
            $schema['minLength'] = $minLength[0]->newInstance()->min;
        }

        $min = $prop->getAttributes(Min::class);
        if (!empty($min)) {
            $schema['minimum'] = $min[0]->newInstance()->min;
        }

        $max = $prop->getAttributes(Max::class);
        if (!empty($max)) {
            $schema['maximum'] = $max[0]->newInstance()->max;
        }

        $pattern = $prop->getAttributes(Pattern::class);
        if (!empty($pattern)) {
            $regex = $pattern[0]->newInstance()->regex;
            // Strip PHP delimiters
            $schema['pattern'] = trim($regex, '/');
        }

        $in = $prop->getAttributes(In::class);
        if (!empty($in)) {
            $schema['enum'] = $in[0]->newInstance()->values;
        }

        $desc = $prop->getAttributes(Description::class);
        if (!empty($desc)) {
            $schema['description'] = $desc[0]->newInstance()->text;
        }

        $example = $prop->getAttributes(Example::class);
        if (!empty($example)) {
            $schema['example'] = $example[0]->newInstance()->value;
        }

        if ($prop->hasDefaultValue()) {
            $default = $prop->getDefaultValue();
            if ($default !== null) {
                $schema['default'] = $default;
            }
        }

        return $schema;
    }

    private static function typeToSchema(ReflectionProperty $prop): array
    {
        $type = $prop->getType();
        if ($type === null) {
            return ['type' => 'string'];
        }

        $typeName = $type->getName();

        return match ($typeName) {
            'int' => ['type' => 'integer'],
            'float' => ['type' => 'number', 'format' => 'float'],
            'bool' => ['type' => 'boolean'],
            'string' => ['type' => 'string'],
            'array' => ['type' => 'array', 'items' => (object) []],
            default => ['type' => 'string'],
        };
    }
}
