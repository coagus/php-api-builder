---
name: mysql-expert
description: Expert on relational database design and usage for PHP APIs — especially MySQL 8+, with awareness of PostgreSQL and SQLite differences. Use when designing schemas, choosing column types, adding indexes, writing migrations, writing PDO queries, handling transactions, or debugging slow queries. Covers normalization, index design, charset/collation, JSON columns, and cross-driver portability for the php-api-builder library.
---

# MySQL Expert — Schema & Query Design

This skill covers the database layer for `php-api-builder`. The library supports MySQL, PostgreSQL, and SQLite via PDO, so patterns must be driver-portable or clearly gated.

## Schema design fundamentals

### Naming
- Tables: `snake_case`, plural — `users`, `user_profiles`, `order_items`.
- Columns: `snake_case` — `first_name`, `created_at`, `user_id`.
- FK columns: `<singular_table>_id` — `user_id`, `category_id`.
- PKs: always `id`, type `BIGINT UNSIGNED AUTO_INCREMENT` (MySQL) / `BIGSERIAL` (PG) / `INTEGER PRIMARY KEY AUTOINCREMENT` (SQLite).
- Join tables: alphabetical singular names — `post_tag`, not `tag_post` or `posts_tags`.

### Column types — pick the smallest that fits

| Need | MySQL | PostgreSQL | SQLite |
|---|---|---|---|
| Boolean | `TINYINT(1)` | `BOOLEAN` | `INTEGER` (0/1) |
| Short string | `VARCHAR(n)` with explicit n | `VARCHAR(n)` or `TEXT` | `TEXT` |
| Email/slug | `VARCHAR(255)` | `VARCHAR(255)` | `TEXT` |
| Long text | `TEXT` or `MEDIUMTEXT` | `TEXT` | `TEXT` |
| Money | `DECIMAL(12,2)` | `NUMERIC(12,2)` | `REAL` (lossy!) or store cents as INT |
| Timestamp | `DATETIME(6)` or `TIMESTAMP` | `TIMESTAMPTZ` | `TEXT` ISO-8601 |
| JSON | `JSON` | `JSONB` | `TEXT` (validate in app) |
| UUID | `CHAR(36)` or `BINARY(16)` | `UUID` native | `TEXT` |
| Enum-ish | `VARCHAR(32)` + CHECK | `ENUM` or CHECK | `TEXT` + CHECK |

**Avoid** `FLOAT`/`REAL` for money. Use `DECIMAL` or store as integer cents.

**Avoid** MySQL's `ENUM` type — changes require `ALTER TABLE` and it doesn't port. Use `VARCHAR` + app-level validation (a PHP `enum`) + a DB `CHECK` constraint if you want a safety net.

### Nullability

Default to `NOT NULL`. A nullable column is a semantic statement: "this value is genuinely unknown sometimes". Most columns are not that.

```sql
name         VARCHAR(100) NOT NULL,
email        VARCHAR(255) NOT NULL,
phone        VARCHAR(20)  NULL,          -- genuinely optional
deleted_at   DATETIME(6)  NULL,          -- only set on soft delete
```

### Charset and collation (MySQL-specific)

Always `utf8mb4` + `utf8mb4_0900_ai_ci` (MySQL 8) or `utf8mb4_unicode_ci` (earlier). Never `utf8` (which is only 3-byte).

```sql
CREATE TABLE users (
    ...
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
```

### Foreign keys

Always declare them explicitly. Set `ON DELETE` behavior consciously:

```sql
FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE     -- child deleted when parent deleted
    ON DELETE RESTRICT    -- block parent deletion if children exist
    ON DELETE SET NULL    -- child's FK set to NULL (only if column is nullable)
```

Default choice: `ON DELETE RESTRICT`. Only `CASCADE` when the child makes no sense without the parent (e.g., `order_items` cascades with `orders`).

## Index design

### When to index
- **Every FK column** — always.
- **Columns in WHERE predicates** — if the table is large.
- **Columns in ORDER BY** — when combined with a common WHERE.
- **Unique business identifiers** — `email`, `slug`, `username` via `UNIQUE` constraint.

### When NOT to index
- Small tables (< 10k rows) — full scan is fine.
- Columns with very low cardinality (boolean-like).
- Every column "just in case" — indexes slow down writes and bloat storage.

### Composite indexes

Order matters. Put the most selective column first, or the one that's always present in WHERE:

```sql
-- Good for queries like WHERE tenant_id = ? AND status = ? ORDER BY created_at DESC
CREATE INDEX idx_orders_tenant_status_created ON orders(tenant_id, status, created_at DESC);
```

The **leftmost prefix rule**: an index on `(a, b, c)` can serve queries on `a`, `(a,b)`, `(a,b,c)` — but not `b` alone or `(b,c)`.

### Soft-delete index gotcha

`#[SoftDelete]` adds `deleted_at`. Every query implicitly filters `deleted_at IS NULL`. Indexes should account for it:

```sql
-- If you soft-delete and filter by user_id a lot:
CREATE INDEX idx_posts_user_active ON posts(user_id, deleted_at);
```

Or in MySQL 8 / PG, use a partial index (PG) or functional index.

## Migrations

Write migrations as SQL files, one per change, named with a timestamp prefix:

```
migrations/
├── 20260418_1200_create_users.sql
├── 20260418_1230_create_posts.sql
└── 20260419_0900_add_slug_to_posts.sql
```

Rules:
- **One logical change per file.** Creating a table with 3 columns is one change; adding a column later is a separate file.
- **Reversible when possible.** Pair with a `.down.sql` file, or document the rollback in a comment at the top.
- **Never edit a migration after it has been deployed.** Write a new one.
- **Prefer additive changes.** Add columns as nullable first, backfill, then tighten to NOT NULL in a second migration.

### Safe ALTER patterns (MySQL)

| Operation | Safe? | Notes |
|---|---|---|
| Add nullable column | Safe, online (MySQL 8) | Default = NULL, no lock |
| Add NOT NULL column with default | Safe in MySQL 8 | Instant in 8.0.12+ |
| Add column with large default | Risky | Full table rewrite |
| Drop column | Unsafe without prep | Rename first, deploy, then drop later |
| Add index | Safe, online | But takes time on large tables |
| Change column type | Risky | Full rewrite; use `pt-online-schema-change` for big tables |

### Multi-driver migrations

If the library must support MySQL + PG + SQLite, keep schema minimal and portable:
- Use `BIGINT` for IDs (maps cleanly to all three).
- Avoid vendor-specific types (`JSON`, `ENUM`, `ARRAY`, `UUID` native).
- Use `CHECK` constraints instead of `ENUM`.
- Keep timestamps as `DATETIME(6)` / `TIMESTAMPTZ` / `TEXT` and let PDO coerce.

## PDO patterns (the only way to query in this library)

### Always parameterize

```php
// GOOD
$stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? AND active = ?');
$stmt->execute([$email, true]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// GOOD (named)
$stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email');
$stmt->execute(['email' => $email]);

// CATASTROPHIC — SQL injection
$rows = $pdo->query("SELECT * FROM users WHERE email = '{$email}'");
```

### PDO options you always want

```php
$pdo = new \PDO($dsn, $user, $pass, [
    \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,    // throw on error
    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
    \PDO::ATTR_EMULATE_PREPARES   => false,                      // real prepared statements
    \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",        // MySQL only
]);
```

`ATTR_EMULATE_PREPARES => false` is critical — emulation means PHP does the escaping, which has edge cases. Real prepared statements delegate to the DB engine.

### Transactions

```php
$pdo->beginTransaction();
try {
    $pdo->prepare('INSERT INTO orders ...')->execute([...]);
    $pdo->prepare('INSERT INTO order_items ...')->execute([...]);
    $pdo->commit();
} catch (\Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
```

The library exposes `Connection::transaction(fn () => ...)` — prefer that wrapper, it handles commit/rollback/rethrow for you.

### `IN (?)` bindings

PDO doesn't expand arrays. Generate placeholders:

```php
$ids = [1, 2, 3];
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("SELECT * FROM users WHERE id IN ({$placeholders})");
$stmt->execute($ids);
```

Or use the library's Query Builder's `whereIn()` — which does this safely.

## Query performance

### `EXPLAIN` everything that looks slow
```sql
EXPLAIN SELECT ... FROM orders o JOIN users u ON u.id = o.user_id WHERE ...;
```
Look for `type: ALL` (full scan) — red flag. Want `const`, `ref`, or `range`.

### Common N+1 trap

```php
// BAD — one query per user
foreach ($users as $user) {
    $user->orders = Order::query()->where('user_id', $user->id)->get();
}

// GOOD — eager loading, one query for users + one for all orders
$users = User::query()->with('orders')->get();
```

Always prefer `->with()` when listing relationships.

### LIMIT + OFFSET pagination pitfall

For deep pagination (page 1000+), `OFFSET 20000 LIMIT 20` forces the DB to materialize and discard 20k rows. Use keyset (cursor) pagination for infinite scroll:

```sql
-- First page
SELECT * FROM posts WHERE status = 'published' ORDER BY id DESC LIMIT 20;

-- Next page (client sends last seen id)
SELECT * FROM posts WHERE status = 'published' AND id < :lastId ORDER BY id DESC LIMIT 20;
```

## SQL injection defense

- Parameterize. Always.
- Never interpolate identifiers (table/column names) from user input. If you must pivot on a column name, validate against an allowlist first.
- The LIKE operator's `%` and `_` need escaping if user input is used as a literal match: `str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $input)`.
- `ORDER BY $col ASC` with user-supplied `$col` — allowlist only. `in_array($col, ['id', 'name', 'created_at'], true)` or reject.

## Driver-specific notes

### MySQL
- `utf8mb4` charset, not `utf8`.
- Use InnoDB engine (default in MySQL 8).
- `DATETIME` for naive times; `TIMESTAMP` auto-converts to UTC.
- JSON type supported but indexes require generated columns.

### PostgreSQL
- Case-sensitive identifier quoting — avoid uppercase identifiers.
- `TIMESTAMPTZ` handles timezones correctly; prefer over `TIMESTAMP`.
- `JSONB` > `JSON` for indexing and performance.
- Use `RETURNING` clause on INSERT/UPDATE/DELETE for last-value retrieval.

### SQLite
- Type affinity, not strict types — a "INTEGER" column happily stores a string.
- One writer at a time; enable WAL mode for better concurrency: `PRAGMA journal_mode=WAL;`.
- No `ALTER TABLE ... DROP COLUMN` until 3.35; use table recreation.
- No `DECIMAL` — store cents as INTEGER.

## What "done" looks like

A schema that normalizes to at least 3NF, uses `NOT NULL` by default, indexes every FK and frequent-WHERE column, avoids vendor lock-in when portability matters, uses parameterized PDO everywhere, and has migrations that are additive, reversible, and never modified after deployment.
