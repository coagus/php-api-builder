<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\Attributes;

use Attribute;

/**
 * Declares a middleware that runs on a specific resource class or method.
 *
 * The attribute is resolved at dispatch time (see MiddlewareResolver). The
 * declared class must implement `MiddlewareInterface`. Parameterized
 * construction is supported via named arguments:
 *
 *   #[Middleware(RateLimitMiddleware::class, limit: 10, windowSeconds: 60)]
 *
 * The attribute is repeatable — endpoints can stack multiple middlewares
 * that run in declaration order.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Middleware
{
    /** @var array<string, mixed> Named arguments passed to the middleware constructor. */
    public readonly array $args;

    public function __construct(
        public readonly string $class,
        mixed ...$args,
    ) {
        $this->args = $args;
    }
}
