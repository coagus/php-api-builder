<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\Attributes\Middleware;
use Coagus\PhpApiBuilder\Http\Middleware\MiddlewareInterface;
use Coagus\PhpApiBuilder\Http\Middleware\MiddlewareResolver;
use Coagus\PhpApiBuilder\Http\Request;
use Coagus\PhpApiBuilder\Http\Response;

/**
 * Spy middlewares used across these tests. Each instance records its construction
 * arguments and its invocation order via a static log.
 */
final class MwSpyLog
{
    /** @var list<array{class: string, tag: string|null, args: array<string, mixed>}> */
    public static array $events = [];
}

final class MwSpyA implements MiddlewareInterface
{
    public function __construct(public ?string $tag = null)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        MwSpyLog::$events[] = ['class' => self::class, 'tag' => $this->tag, 'args' => []];

        return $next($request);
    }
}

final class MwSpyB implements MiddlewareInterface
{
    public function __construct(public ?string $tag = null, public int $limit = 0)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        MwSpyLog::$events[] = [
            'class' => self::class,
            'tag' => $this->tag,
            'args' => ['limit' => $this->limit],
        ];

        return $next($request);
    }
}

final class NotAMiddleware
{
}

#[Middleware(MwSpyA::class)]
final class MwFixtureResource
{
    #[Middleware(MwSpyB::class, tag: 'method', limit: 10)]
    public function get(): void
    {
    }

    public function post(): void
    {
    }
}

#[Middleware(MwSpyA::class, tag: 'outer')]
#[Middleware(MwSpyB::class, tag: 'inner', limit: 3)]
final class MwStackedResource
{
    public function get(): void
    {
    }
}

final class MwBadResource
{
    #[Middleware(NotAMiddleware::class)]
    public function get(): void
    {
    }
}

beforeEach(function () {
    MwSpyLog::$events = [];
});

test('class-level attribute is resolved when no method-level attribute exists', function () {
    $middlewares = MiddlewareResolver::resolveFor(MwFixtureResource::class, 'post');

    expect($middlewares)->toHaveCount(1)
        ->and($middlewares[0])->toBeInstanceOf(MwSpyA::class);
});

test('class-level runs before method-level in the resolved order', function () {
    $middlewares = MiddlewareResolver::resolveFor(MwFixtureResource::class, 'get');

    expect($middlewares)->toHaveCount(2)
        ->and($middlewares[0])->toBeInstanceOf(MwSpyA::class)
        ->and($middlewares[1])->toBeInstanceOf(MwSpyB::class);
});

test('named arguments are forwarded to the middleware constructor', function () {
    $middlewares = MiddlewareResolver::resolveFor(MwFixtureResource::class, 'get');

    /** @var MwSpyB $second */
    $second = $middlewares[1];
    expect($second->tag)->toBe('method')
        ->and($second->limit)->toBe(10);
});

test('IS_REPEATABLE attributes stack in declaration order', function () {
    $middlewares = MiddlewareResolver::resolveFor(MwStackedResource::class, 'get');

    expect($middlewares)->toHaveCount(2)
        ->and($middlewares[0])->toBeInstanceOf(MwSpyA::class)
        ->and($middlewares[1])->toBeInstanceOf(MwSpyB::class);
    expect($middlewares[0]->tag)->toBe('outer');
    expect($middlewares[1]->tag)->toBe('inner');
});

test('rejects a class that does not implement MiddlewareInterface', function () {
    MiddlewareResolver::resolveFor(MwBadResource::class, 'get');
})->throws(RuntimeException::class, 'must implement');

test('returns an empty list when neither class nor method declares middleware', function () {
    $middlewares = MiddlewareResolver::resolveFor(NotAMiddleware::class, 'nonexistent');

    expect($middlewares)->toBe([]);
});
