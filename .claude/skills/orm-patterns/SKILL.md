---
name: orm-patterns
description: Expert on ORM design patterns and anti-patterns — Active Record vs Data Mapper, identity map, unit of work, eager vs lazy loading, N+1 detection and cure, query builder composition, relationship cascading, scopes, soft deletes, optimistic locking, and performance profiling. Use when writing, reviewing, or optimizing ORM-layer code in the php-api-builder library to produce performant, maintainable data access code.
---

# ORM Patterns — Write Data Access That Scales

The library uses the **Active Record** pattern (Entity = row + behavior). This skill covers how to use it well, avoid the classic traps, and optimize without breaking abstraction.

## The cardinal sin: N+1

The single most common and destructive ORM bug. It looks innocent:

```php
// BAD — 1 query for users + N queries for orders (N+1)
$users = User::all();
foreach ($users as $user) {
    echo count($user->orders);   // hits DB each iteration
}
```

For 100 users, this is 101 queries. In prod under load, it becomes a cascade outage.

### The cure: eager loading

```php
// GOOD — 2 queries total, regardless of N
$users = User::query()->with('orders')->get();
foreach ($users as $user) {
    echo count($user->orders);   // already loaded
}
```

Nested: `->with('orders', 'orders.items', 'orders.items.product')`.

### How to detect N+1

Three techniques:
1. **Query log**. In dev, log every query with its caller. Run an endpoint; count queries. If it scales with result size, you have N+1.
2. **`EXPLAIN` in integration tests.** Assert the number of queries per endpoint: `expect(Connection::queryCount())->toBe(2)`.
3. **Manual review.** Any loop containing `$entity->relation` is suspicious. If the relation wasn't eager-loaded, it's N+1.

### When NOT to eager-load

Eager loading pays off when you'll access the relation. Don't eager-load "just in case" — it wastes rows. Load only the relations the current operation uses.

## Active Record — when it fits, when it hurts

Active Record couples the entity to its persistence. Good when:
- The entity's behavior is tightly bound to its row (which is usually true for CRUD APIs).
- You want minimal ceremony: `$user->save()` is hard to beat for readability.

Pain points when:
- Complex business logic — the entity becomes a god class.
- Unit testing — you can't easily instantiate an entity without a DB connection.
- Cross-entity invariants — "an order's total must match the sum of its items" doesn't belong on either entity alone.

### Escape hatches within Active Record

- **Services / domain objects** for cross-entity logic. The library's `Service` class is for exactly this.
- **Value objects** for tightly-scoped logic (Money, Address, Coordinates) — `readonly` classes that an entity holds.
- **Repositories** (if needed): a class that owns "how to query for X" — keeps complex queries off the entity.

## Identity Map — load each row once

An identity map ensures that the same row in memory is represented by the same object instance. Prevents:
- Stale reads within a request (update X via one path, read X via another and see old data).
- Duplicate hydration cost.

Check if the library implements one. If it does, use it. If it doesn't, be aware: `User::find(1)` twice may return two different objects. Don't compare with `===` across calls — use `->id === $otherId` or provide an `equals()` method.

## Unit of Work — batch writes

Instead of saving on every mutation, accumulate changes and flush once:

```php
// Acceptable for scripts / bulk operations
Connection::transaction(function () use ($items) {
    foreach ($items as $item) {
        $o = new OrderItem();
        $o->fill($item);
        $o->save();
    }
});
```

Even better, when the ORM supports it, batch-insert:
```php
OrderItem::insertMany($itemsArray);  // single INSERT with multiple VALUES
```

## Lazy loading

Accessing `$user->orders` triggers a query if not eager-loaded. Useful for flexibility but lethal in loops (N+1).

Rule of thumb:
- **Controller / action code**: eager-load what you'll touch.
- **Rare/conditional access**: lazy is fine.
- **Inside a loop**: never lazy.

## Query Builder composition

Build queries modularly. Don't write one massive query; compose small methods:

```php
class User extends Entity
{
    public static function scopeActive(QueryBuilder $q): QueryBuilder
    {
        return $q->where('active', true)->whereNull('deleted_at');
    }

    public static function scopeRecent(QueryBuilder $q, int $days = 30): QueryBuilder
    {
        return $q->where('created_at', '>=', date('Y-m-d', strtotime("-{$days} days")));
    }

    public static function scopeInRole(QueryBuilder $q, int $roleId): QueryBuilder
    {
        return $q->where('role_id', $roleId);
    }
}

// Usage
$users = User::query()->active()->recent(7)->inRole(2)->get();
```

Scopes are reusable, testable, and read like the domain. Prefer scopes over ad-hoc `->where()` chains repeated across the codebase.

## Relationships — pick the right kind

| Kind | Use when |
|---|---|
| `BelongsTo` | The FK column lives on *this* entity (many → one) |
| `HasOne` | The FK lives on the *other* entity (one → one) |
| `HasMany` | The FK lives on the other entity (one → many) |
| `BelongsToMany` | Join table in between (many → many) |

### Don't normalize into a `HasMany` of one

If `User` always has exactly one `Profile`, it's `HasOne`, not `HasMany`. Modeling it as a `HasMany` leaks into a hundred calls that need `->first()`.

### Polymorphic: last resort

Polymorphic relations (`commentable_type`, `commentable_id`) sacrifice referential integrity and index efficiency. Only use when the alternative is five near-identical `comments_X` tables. Most of the time, table inheritance or an explicit `Comment` table with FK columns is cleaner.

## Cascade behavior — always explicit

```php
// In migration or schema:
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT
FOREIGN KEY (tag_id)  REFERENCES tags(id)  ON DELETE SET NULL
```

- **CASCADE**: child deleted when parent deleted (e.g., order_items when order deleted).
- **RESTRICT / NO ACTION**: block parent deletion if children exist.
- **SET NULL**: null the FK (column must be nullable).

Don't rely on ORM-level `beforeDelete` hooks to cascade. DB-level cascade is atomic and enforced even if app bypasses the ORM.

## Soft deletes

The library has `#[SoftDelete]`. When applied:
- `delete()` sets `deleted_at = NOW()` instead of removing.
- Default queries filter `WHERE deleted_at IS NULL`.
- `withTrashed()` / `onlyTrashed()` to bypass.

### Gotchas

- **Unique constraints break.** If `email` is unique and a soft-deleted row exists with `ada@x.com`, a new user with the same email fails. Fix with partial unique index:
  ```sql
  CREATE UNIQUE INDEX users_email_unique ON users (email) WHERE deleted_at IS NULL;
  ```
- **FK cascades interact oddly.** If `User.deleted_at IS NOT NULL` but its `Order` rows still exist and reference it, `SELECT u.* FROM users u WHERE u.id = orders.user_id` returns nothing under the default scope. Think through whether children should also be soft-deleted.
- **Indexes must account for `deleted_at`.** Otherwise every list query scans soft-deleted rows.

## Optimistic locking

For concurrent updates without pessimistic row locks:

```php
#[Table('orders')]
class Order extends Entity
{
    #[PrimaryKey]
    public private(set) int $id;

    public int $version = 0;   // increments on each update

    public string $status;

    protected function beforeUpdate(): void
    {
        $this->version++;
    }
}

// On update, the ORM issues:
// UPDATE orders SET status=?, version=? WHERE id=? AND version=<old>
// If 0 rows affected, someone else changed it first — throw StaleEntityException.
```

Use when the business is "last writer wins is wrong". Skip when simple overwrites are acceptable.

## Bulk operations — don't hydrate

Avoid loading entities into memory just to update/delete:

```php
// BAD — loads all matching rows into PHP, then calls save()/delete() in a loop
User::query()->where('active', false)->each(fn ($u) => $u->delete());

// GOOD — one UPDATE/DELETE at the DB
User::query()->where('active', false)->delete();
User::query()->where('active', false)->update(['archived_at' => date('c')]);
```

Hydration has real cost: PHP object allocation, property hooks firing, memory pressure. Bulk DB ops skip all of it.

## Raw SQL escape hatch — safely

When the Query Builder can't express what you need:

```php
$rows = Connection::getInstance()->query(
    'SELECT u.id, COUNT(o.id) AS total
     FROM users u
     LEFT JOIN orders o ON o.user_id = u.id
     WHERE u.created_at > ?
     GROUP BY u.id
     HAVING total > ?',
    [$since, 5]
);
```

Rules:
- Always parameterize.
- Wrap in a repository method or scope so callers don't hand-write SQL.
- Write a test that covers the expected shape.
- Document why the ORM wasn't enough.

## Transactions — correctness first

```php
Connection::transaction(function () use ($payload) {
    $order = Order::create($payload['order']);
    foreach ($payload['items'] as $itemData) {
        $order->items()->create($itemData);
    }
    Inventory::decrement($payload['items']);
    Mailer::sendOrderConfirmation($order);  // BAD — side effect in transaction
});
```

Last line is a classic bug: if the transaction rolls back, the email was already sent. Side effects go **after** the commit:

```php
Connection::transaction(function () use ($payload, &$order) {
    $order = Order::create($payload['order']);
    // ... DB work only
});
Mailer::sendOrderConfirmation($order);
```

Or queue the side effect inside the transaction and have a worker pick it up after commit.

## Connection pooling / per-request connection

- The library uses a singleton `Connection`. Fine for single-request PHP-FPM workers.
- Long-running workers (Swoole, RoadRunner) — reset state between requests; watch for transaction leaks.
- Never cache `Connection` instances across requests without resetting.

## Profiling and observability

- Log every query in dev with a `requestId`. Review per-endpoint query counts.
- Production: sample slow queries (> 100ms) and pipe to the logger.
- Assert on query count in critical integration tests:
  ```php
  it('lists users with orders in 2 queries', function () {
      $count = Connection::withQueryCounting(fn () =>
          $this->getJson('/api/v1/users?include=orders')
      );
      expect($count)->toBe(2);
  });
  ```

## Anti-pattern catalog

- **Fat entity**: entity with 500 lines of business logic. Extract to services / value objects.
- **Fluent cancer**: `->where()->where()->where()->where()->join()->leftJoin()->groupBy()->having()->orderBy()` spanning 15 lines and never reused. Extract to a scope or repository method.
- **`SELECT *` everywhere**: not actually an ORM sin (most ORMs need all columns to hydrate), but in high-traffic endpoints with wide tables, `->select(['id', 'name'])` saves real bandwidth.
- **Querying in views / templates**: every view-layer `{{ user.orders.count }}` is a query. Pre-fetch aggregates.
- **Accumulating in memory**: `User::all()` on a table with 1M rows. Use `->chunk(500, fn ($rows) => ...)` or keyset pagination.
- **Cross-entity transactions at the wrong boundary**: wrapping the whole HTTP request in a transaction instead of just the DB ops.

## Checklist for ORM code

- [ ] No N+1: all relations accessed in loops are eager-loaded.
- [ ] Scopes used for reusable query fragments.
- [ ] FKs declared with explicit ON DELETE behavior.
- [ ] Soft delete (if used) has partial unique indexes.
- [ ] Bulk operations go through `->update()`/`->delete()`, not per-entity hydration.
- [ ] Transactions contain only DB work; side effects come after commit.
- [ ] Raw SQL is wrapped and justified.
- [ ] Integration tests assert query counts for critical paths.
- [ ] Entities don't carry business logic that spans multiple tables.
- [ ] Relationships are the right kind (BelongsTo / HasOne / HasMany / BelongsToMany).
