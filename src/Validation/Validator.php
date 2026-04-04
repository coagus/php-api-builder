<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\Validation;

use Coagus\PhpApiBuilder\ORM\Entity;
use Coagus\PhpApiBuilder\Validation\Attributes\DefaultValue;
use Coagus\PhpApiBuilder\Validation\Attributes\Email;
use Coagus\PhpApiBuilder\Validation\Attributes\In;
use Coagus\PhpApiBuilder\Validation\Attributes\Max;
use Coagus\PhpApiBuilder\Validation\Attributes\MaxLength;
use Coagus\PhpApiBuilder\Validation\Attributes\Min;
use Coagus\PhpApiBuilder\Validation\Attributes\MinLength;
use Coagus\PhpApiBuilder\Validation\Attributes\Pattern;
use Coagus\PhpApiBuilder\Validation\Attributes\Required;
use ReflectionClass;
use ReflectionProperty;

class Validator
{
    public static function validate(Entity $entity): ?array
    {
        $ref = new ReflectionClass($entity);
        $errors = [];

        foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $fieldErrors = self::validateProperty($prop, $entity);
            if (!empty($fieldErrors)) {
                $errors[$prop->getName()] = $fieldErrors;
            }
        }

        return empty($errors) ? null : $errors;
    }

    private static function validateProperty(ReflectionProperty $prop, Entity $entity): array
    {
        $errors = [];
        $name = $prop->getName();
        $isInitialized = $prop->isInitialized($entity);
        $value = $isInitialized ? $prop->getValue($entity) : null;

        if (!empty($prop->getAttributes(Required::class))) {
            if (!$isInitialized || $value === null || $value === '') {
                $errors[] = "The field '{$name}' is required.";
                return $errors;
            }
        }

        if (!$isInitialized || $value === null) {
            return $errors;
        }

        if (!empty($prop->getAttributes(Email::class))) {
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "The field '{$name}' must be a valid email address.";
            }
        }

        $maxLengthAttrs = $prop->getAttributes(MaxLength::class);
        if (!empty($maxLengthAttrs)) {
            $maxLength = $maxLengthAttrs[0]->newInstance()->max;
            if (is_string($value) && mb_strlen($value) > $maxLength) {
                $errors[] = "The field '{$name}' must not exceed {$maxLength} characters.";
            }
        }

        $minLengthAttrs = $prop->getAttributes(MinLength::class);
        if (!empty($minLengthAttrs)) {
            $minLength = $minLengthAttrs[0]->newInstance()->min;
            if (is_string($value) && mb_strlen($value) < $minLength) {
                $errors[] = "The field '{$name}' must be at least {$minLength} characters.";
            }
        }

        $minAttrs = $prop->getAttributes(Min::class);
        if (!empty($minAttrs)) {
            $min = $minAttrs[0]->newInstance()->min;
            if (is_numeric($value) && $value < $min) {
                $errors[] = "The field '{$name}' must be at least {$min}.";
            }
        }

        $maxAttrs = $prop->getAttributes(Max::class);
        if (!empty($maxAttrs)) {
            $max = $maxAttrs[0]->newInstance()->max;
            if (is_numeric($value) && $value > $max) {
                $errors[] = "The field '{$name}' must not exceed {$max}.";
            }
        }

        $patternAttrs = $prop->getAttributes(Pattern::class);
        if (!empty($patternAttrs)) {
            $regex = $patternAttrs[0]->newInstance()->regex;
            if (is_string($value) && !preg_match($regex, $value)) {
                $errors[] = "The field '{$name}' does not match the required pattern.";
            }
        }

        $inAttrs = $prop->getAttributes(In::class);
        if (!empty($inAttrs)) {
            $allowed = $inAttrs[0]->newInstance()->values;
            if (!in_array($value, $allowed, true)) {
                $allowedStr = implode(', ', $allowed);
                $errors[] = "The field '{$name}' must be one of: {$allowedStr}.";
            }
        }

        return $errors;
    }
}
