---
name: php-expert
description: Expert-level PHP 8.4 development practices. Use when writing, reviewing, or refactoring modern PHP — especially for this library which relies on property hooks, asymmetric visibility, typed properties, and PHP attributes. Covers PSR-12 style, strict types, exception design, enum usage, readonly properties, first-class callable syntax, and common pitfalls to avoid.
---

# PHP 8.4 Expert — Development Best Practices

This skill teaches idiomatic, safe, and modern PHP for the `php-api-builder` codebase. Every new file should look like it was written by someone who deeply understands PHP 8.4.

## Non-negotiable file header

Every new PHP file:

```php
<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\Foo\Bar;   // match PSR-4 to src/Foo/Bar/
```

No exceptions. `strict_types` is not optional — it catches silent type coercions that bite hard in production.

## Property hooks (PHP 8.4)

Prefer property hooks over getter/setter methods when you need to sanitize or validate on assignment:

```php
// GOOD — hook sanitizes on every assignment
public string $email {
    set => strtolower(trim($value));
}

// GOOD — hook validates and throws
public float $price {
    set {
        if ($value < 0) {
            throw new \InvalidArgumentException('Price must be non-negative');
        }
        $this->price = round($value, 2);
    }
}

// BAD — methods work but obscure intent and break property access semantics
public function setEmail(string $v): void { $this->email = strtolower(trim($v)); }
```

Two syntaxes:
- **Short form**: `set => expression` — for simple transforms. The result is what gets stored.
- **Long form**: `set { ... $this->prop = $value; }` — for multi-step logic. You must assign `$this->prop` explicitly.

`get` hooks are rarely needed; use them only for derived values without backing storage.

## Asymmetric visibility (PHP 8.4)

Use `public private(set)` (or `public protected(set)`) when callers should read a property but only the class itself should write it:

```php
class Entity
{
    #[PrimaryKey]
    public private(set) int $id;   // public read, private write

    public protected(set) \DateTimeImmutable $createdAt;
}
```

This replaces the old pattern of `private $id` + `public function getId()`. Less ceremony, same safety.

## Typed properties

Every property must have a type. No `mixed` unless truly unavoidable. No untyped properties.

```php
// GOOD
public string $name;
public ?int $categoryId = null;
public array $tags = [];                              // typed, defaulted
public \DateTimeImmutable $createdAt;
public Status $status = Status::Draft;                // enum type

// BAD
public $name;                                         // no type
public mixed $data;                                   // mixed without reason
```

For collections, prefer typed array docblocks to help static analysis:

```php
/** @var list<Review> */
public array $reviews;
```

## readonly and readonly classes

Use `readonly` for value objects and DTOs. Assignment happens once (in the constructor) and is permanent.

```php
final readonly class Money
{
    public function __construct(
        public int $amountCents,
        public string $currency,
    ) {}

    public function plus(Money $other): self
    {
        if ($other->currency !== $this->currency) {
            throw new \DomainException('Cannot add different currencies');
        }
        return new self($this->amountCents + $other->amountCents, $this->currency);
    }
}
```

`readonly` + constructor promotion = maximum signal, minimum noise.

## Enums (prefer over string/int constants)

```php
enum OrderStatus: string
{
    case Pending   = 'pending';
    case Paid      = 'paid';
    case Shipped   = 'shipped';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Shipped, self::Cancelled => true,
            default                         => false,
        };
    }
}
```

Backed enums (`: string` or `: int`) for when you need to persist to DB or emit in JSON. Pure enums for in-memory state machines.

## Attribute design

When designing a new attribute for this library:

```php
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class MaxLength
{
    public function __construct(public readonly int $length)
    {
        if ($length < 1) {
            throw new \InvalidArgumentException('MaxLength must be positive');
        }
    }
}
```

Rules:
- Mark with `final` — attributes should not be extended.
- Target only the scope they make sense in (`TARGET_PROPERTY`, `TARGET_CLASS`, `TARGET_METHOD`). Never `TARGET_ALL`.
- Validate constructor args — an invalid attribute should fail at class load, not at runtime.
- `readonly` constructor promotion for attribute properties.
- No side effects in the constructor — attributes should be pure data.

## Exception design

Use specific exception classes, not `\Exception` or `\RuntimeException` generically:

```php
// In src/Exceptions/
final class EntityNotFoundException extends \RuntimeException
{
    public static function forId(string $entity, int|string $id): self
    {
        return new self("Entity {$entity} with id {$id} not found");
    }
}
```

Throw:
- `\InvalidArgumentException` — bad argument from caller.
- `\DomainException` — operation violates business rules.
- `\LogicException` — programmer error (should never happen at runtime).
- Custom domain exceptions — for errors the caller will catch and handle.

Never swallow exceptions silently. If you catch, either rethrow with context or log with full stack.

## Null safety

```php
// GOOD — null-safe operator, no chains of isset checks
$slug = $post?->category?->slug ?? 'uncategorized';

// GOOD — null coalescing assignment
$config['timeout'] ??= 30;

// BAD — C-style defensive programming
if (isset($post) && isset($post->category) && isset($post->category->slug)) {
    $slug = $post->category->slug;
}
```

## Immutability by default

Prefer returning new instances over mutating:

```php
// GOOD
public function withTimeout(int $seconds): self
{
    return new self($this->host, $this->port, $seconds);
}

// SUSPICIOUS (sometimes necessary, but think twice)
public function setTimeout(int $seconds): void
{
    $this->timeout = $seconds;
}
```

## PSR-12 style essentials

- 4 spaces, no tabs.
- Opening brace of class/function on a new line; of control structures on the same line.
- One statement per line.
- Short array syntax `[]`, never `array()`.
- Imports grouped and sorted: PHP core, third-party, internal — each group alphabetized.
- No unused imports.
- No trailing whitespace.

## Common pitfalls to avoid

1. **`==` vs `===`** — always `===` unless you genuinely need loose comparison.
2. **`empty()` on typed properties** — misleading; `null`, `0`, `'0'`, `[]` are all "empty". Be explicit: `$x === null`, `$x === ''`, `count($x) === 0`.
3. **`array_key_exists` vs `isset`** — `isset` returns false on `null` values. Use `array_key_exists` when you need to distinguish "missing" from "null".
4. **String interpolation in SQL** — never. Parameterize.
5. **`json_encode` without flags** — use `JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES` by default.
6. **`date()` without timezone** — use `\DateTimeImmutable` with explicit `\DateTimeZone('UTC')`.
7. **`md5`/`sha1` for passwords** — only `password_hash($pw, PASSWORD_ARGON2ID)`.
8. **Mutable state in singletons** — it will bite you in tests. Prefer dependency injection.

## Closure and first-class callable syntax

```php
// First-class callable (PHP 8.1+) — cleaner than Closure::fromCallable
$strlen = strlen(...);
$methodRef = $this->doSomething(...);

// Prefer over creating anonymous functions just to wrap a method
$ids = array_map($this->extractId(...), $items);
```

## When in doubt

1. Look at nearby existing code — match its style and idioms.
2. Run `php -l <file>` after edits.
3. Prefer boring, explicit code over clever tricks.

## What "done" looks like

A PR that looks like it was written by one person, matches the repo's existing patterns, has `declare(strict_types=1)`, uses typed properties and attributes correctly, has no unused imports, and would pass a strict static analyzer without warnings.
