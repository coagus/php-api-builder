<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\Http\Middleware;

use Coagus\PhpApiBuilder\Attributes\Middleware as MiddlewareAttribute;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

/**
 * Resolves `#[Middleware(...)]` attributes declared on a resource class and/or
 * its handler method into a list of `MiddlewareInterface` instances.
 *
 * The attribute may be declared at class level, method level, or both, and is
 * repeatable. Class-level attributes run before method-level ones. Within a
 * single target, attributes run in declaration order.
 *
 * Parameterized construction is supported via named arguments:
 *
 *   #[Middleware(RateLimitMiddleware::class, limit: 10, windowSeconds: 60)]
 *
 * The declared class MUST implement `MiddlewareInterface`; otherwise a
 * `RuntimeException` is thrown at resolve time (fail loud, not silent).
 */
final class MiddlewareResolver
{
    /**
     * @return list<MiddlewareInterface>
     */
    public static function resolveFor(string $class, string $method): array
    {
        $classAttrs = self::attributesOnClass($class);
        $methodAttrs = self::attributesOnMethod($class, $method);

        return self::instantiate([...$classAttrs, ...$methodAttrs]);
    }

    /**
     * @return list<ReflectionAttribute<MiddlewareAttribute>>
     */
    private static function attributesOnClass(string $class): array
    {
        $ref = new ReflectionClass($class);

        return $ref->getAttributes(MiddlewareAttribute::class, ReflectionAttribute::IS_INSTANCEOF);
    }

    /**
     * @return list<ReflectionAttribute<MiddlewareAttribute>>
     */
    private static function attributesOnMethod(string $class, string $method): array
    {
        if (!method_exists($class, $method)) {
            return [];
        }

        $ref = new ReflectionMethod($class, $method);

        return $ref->getAttributes(MiddlewareAttribute::class, ReflectionAttribute::IS_INSTANCEOF);
    }

    /**
     * @param list<ReflectionAttribute<MiddlewareAttribute>> $attributes
     * @return list<MiddlewareInterface>
     */
    private static function instantiate(array $attributes): array
    {
        $middlewares = [];
        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();
            $middlewares[] = self::buildFromAttribute($instance);
        }

        return $middlewares;
    }

    private static function buildFromAttribute(MiddlewareAttribute $attribute): MiddlewareInterface
    {
        $class = $attribute->class;

        if (!class_exists($class)) {
            throw new RuntimeException(
                "#[Middleware] references class [{$class}] which does not exist."
            );
        }

        if (!is_subclass_of($class, MiddlewareInterface::class)) {
            throw new RuntimeException(
                "#[Middleware] class [{$class}] must implement " . MiddlewareInterface::class . '.'
            );
        }

        return new $class(...$attribute->args);
    }
}
