---
name: testing-pest
description: Expert on Pest PHP testing — syntax, architecture, fixtures, test doubles, dataset providers, and test organization for unit/feature/integration tiers. Use when writing, reviewing, or refactoring tests for the php-api-builder library, or when deciding which tier a test belongs in.
---

# Pest Testing — How to Write Tests That Matter

This skill covers Pest PHP 3.x (which wraps PHPUnit). Every non-trivial change to the library ships with tests.

## The three tiers — know which one you're writing

| Tier | Under | Touches real I/O? | Speed | When to use |
|---|---|---|---|---|
| **Unit** | `tests/Unit/` | No (pure PHP, no DB, no HTTP, no filesystem) | Fast (< 10ms) | Single class, pure logic — validators, attribute parsers, query builders, helpers |
| **Feature** | `tests/Feature/` | HTTP layer, fake DB | Medium (< 100ms) | End-to-end request/response flows, middleware chains, routing |
| **Integration** | `tests/Integration/` | Real DB (SQLite in-memory usually) | Slow (< 500ms) | ORM behavior, transactions, migrations |

If a test touches the DB, it's **not** a unit test. If a test instantiates the whole app, it's **not** a unit test. Mis-tiering makes the suite slow and fragile.

## Basic Pest syntax

```php
<?php

declare(strict_types=1);

it('lowercases and trims email on assignment', function () {
    $user = new User();
    $user->email = '  User@Example.COM  ';

    expect($user->email)->toBe('user@example.com');
});

test('a property hook rejects negative prices', function () {
    $product = new Product();

    expect(fn () => $product->price = -1.0)
        ->toThrow(InvalidArgumentException::class);
});
```

- `it('does X', ...)` reads like "it does X". Standard.
- `test('description', ...)` is the same — pick one style per file for consistency.
- `expect(...)` chain: `toBe`, `toEqual`, `toBeTrue`, `toBeNull`, `toHaveCount`, `toThrow`, `toMatchSnapshot`, etc.
- Closures — no `public function testFoo`.

## Grouping with `describe`

```php
describe('User entity', function () {
    it('validates email format', function () { ... });
    it('hashes password on assignment', function () { ... });
    it('trims whitespace from name', function () { ... });
});
```

Group by behavior under test, not by class. A `describe` block is a cohesive cluster of related assertions.

## Datasets — test many inputs with one assertion

```php
it('rejects invalid emails', function (string $input) {
    $user = new User();
    expect(fn () => $user->email = $input)
        ->toThrow(InvalidArgumentException::class);
})->with([
    'empty'       => '',
    'no at'       => 'foo.com',
    'no domain'   => 'foo@',
    'no tld'      => 'foo@bar',
    'spaces'      => 'foo @bar.com',
]);
```

Named keys (`'empty' => ''`) — makes failing test output readable.

## Fixtures and `beforeEach`

```php
beforeEach(function () {
    $this->user = new User(['name' => 'Ada', 'email' => 'ada@test.com']);
});

it('has the fixture values', function () {
    expect($this->user->name)->toBe('Ada');
});
```

Put shared fixtures in `tests/Fixtures/` as pure factories:

```php
// tests/Fixtures/UserFactory.php
final class UserFactory
{
    public static function make(array $overrides = []): User
    {
        $user = new User();
        $user->fill([
            'name'  => 'Ada Lovelace',
            'email' => 'ada@example.com',
            ...$overrides,
        ]);
        return $user;
    }
}
```

Then in tests:
```php
$user = UserFactory::make(['email' => 'custom@test.com']);
```

## Feature tests (HTTP layer)

```php
it('returns 201 and the created user', function () {
    $response = $this->postJson('/api/v1/users', [
        'name'  => 'Ada',
        'email' => 'ada@test.com',
        'password' => 'secret123',
    ]);

    expect($response->getStatusCode())->toBe(201);
    expect($response->json('data.name'))->toBe('Ada');
    expect($response->json('data.password'))->toBeNull();  // hidden by #[Hidden]
});
```

Check the library's `TestCase` or helper trait for `postJson`/`getJson`/`patchJson` equivalents.

## Integration tests (DB)

Use SQLite in-memory for speed and isolation:

```php
// tests/Pest.php — configure once
beforeEach(function () {
    Connection::setInstance(new PDO('sqlite::memory:'));
    Connection::getInstance()->exec(file_get_contents(__DIR__ . '/schema.sql'));
});

afterEach(function () {
    Connection::reset();
});
```

Then:

```php
it('soft deletes a user', function () {
    $user = UserFactory::make();
    $user->save();

    $user->delete();

    expect(User::find($user->id))->toBeNull();                       // hidden by scope
    expect(User::withTrashed()->find($user->id))->not->toBeNull();   // still there
});
```

## Test doubles — stubs, mocks, fakes

Pest doesn't ship with a full mocking framework. Use Mockery (if added) sparingly, or prefer **fakes**:

```php
// Fake — a lightweight in-memory implementation of an interface
class FakeRateLimitStore implements RateLimitStoreInterface
{
    private array $counts = [];
    public function increment(string $key): int { return ++$this->counts[$key]; }
    public function reset(string $key): void { unset($this->counts[$key]); }
}
```

Fakes are more readable and robust than mocks for behavior-heavy tests. Mocks are fine for interactions you want to verify (spy-style).

## What to test

Focus on:
- **Behavior**, not implementation. Assert on outcomes (return value, side effect on state), not on which private methods got called.
- **Boundaries**. Empty inputs, zero, negative, max length, null, very large, special chars.
- **Error paths**. Each `throw` in production code should have a test that triggers it.
- **Contracts**. If a public method promises "returns null when not found", test both branches.

Don't test:
- Framework/library code you don't own.
- Trivial getters/setters with no logic.
- String-equal assertions on error messages (brittle; test error **type** + key data).
- Implementation details that can change without breaking behavior.

## Assertion patterns

### Strict equality
```php
expect($x)->toBe(42);          // identity (===)
expect($x)->toEqual(42);        // equality (==) — avoid unless you really need it
```

### Collections
```php
expect($users)->toHaveCount(3);
expect($users)->toContain($ada);
expect($users)->each(fn ($u) => $u->toBeInstanceOf(User::class));
```

### Exceptions
```php
expect(fn () => riskyCall())
    ->toThrow(DomainException::class, 'specific substring');
```

### JSON shape (feature tests)
```php
expect($response->json())
    ->toMatchArray([
        'data' => ['name' => 'Ada'],
        'meta' => ['total' => 1],
    ]);
```

## Flakiness — zero tolerance

A test is flaky if it sometimes passes and sometimes fails without code change. Causes:
- Time-dependent assertions (`date()` in test + production).
- Randomness without a seed.
- Test order dependence (state leaking between tests).
- External network calls.
- Race conditions in parallel runs.

Fix the root cause. Don't retry flaky tests — delete them or repair them.

## Running tests

```bash
./api test                              # full suite
./vendor/bin/pest                       # same
./vendor/bin/pest --filter='User'       # name filter
./vendor/bin/pest tests/Unit/           # path filter
./vendor/bin/pest --coverage            # with coverage report
./vendor/bin/pest --bail                # stop at first failure
./vendor/bin/pest --parallel            # run across processes
```

## Coverage — use as a signal, not a goal

Coverage tells you what code is **exercised**, not what's **tested**. A file can have 100% line coverage with no real assertions.

Targets:
- Library core: 85%+ line coverage, 95%+ for critical paths (validation, auth, ORM).
- New code in a PR: no new uncovered branches.

Don't chase 100%. A `throw new AssertionError('unreachable')` defensive branch doesn't need a test.

## Test file conventions

- File name: `<ClassName>Test.php` mirrors `src/<ClassName>.php`.
- Namespace: `Tests\Unit\...` / `Tests\Feature\...` / `Tests\Integration\...`.
- One class per file; test file tests one production class.
- `declare(strict_types=1);` at the top.

## Pest-specific niceties

### Higher-order testing
```php
it('returns an array with name and email keys')
    ->expect(User::all()->first()->toArray())
    ->toHaveKeys(['name', 'email']);
```

### Dataset from a closure
```php
->with(function () {
    return User::query()->active()->get()->map(fn ($u) => [$u->id]);
})
```

### Shared architecture tests
```php
// tests/Arch/EntityArchTest.php
arch('entities must extend Entity')
    ->expect('App\Entities')
    ->toExtend(Coagus\PhpApiBuilder\ORM\Entity::class);

arch('no debug calls in src')
    ->expect('Coagus\PhpApiBuilder')
    ->not->toUse(['dd', 'dump', 'var_dump', 'print_r']);
```

Architecture tests are cheap and catch whole categories of bugs.

## What "done" looks like

A test suite that runs under 10 seconds for unit tests, clearly partitions unit/feature/integration, uses named fixtures, asserts on behavior not implementation, has zero flakes, and exercises the error paths as well as the happy paths.
