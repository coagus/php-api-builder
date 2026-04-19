---
name: postgres-expert
description: Expert on PostgreSQL 14+ specific features and idioms — JSONB, TIMESTAMPTZ, UUID, arrays, ENUM types, partial/functional/GIN indexes, CTEs, window functions, RETURNING, upserts, full-text search (tsvector), row-level security, partitioning, EXPLAIN ANALYZE, and PDO pgsql driver specifics. Use when writing, optimizing, or debugging PostgreSQL-specific code for the php-api-builder library.
---

# PostgreSQL Expert — Writing Optimal PG Code

PostgreSQL is more than "MySQL with better features". Use its native capabilities deliberately; don't emulate.

## Data types — use the native ones

| Need | Use | Not |
|---|---|---|
| Unique ID | `UUID` (native) or `BIGSERIAL`/`bigint GENERATED ALWAYS AS IDENTITY` | `CHAR(36)` for UUIDs |
| Timestamp | `TIMESTAMPTZ` (with timezone) | `TIMESTAMP` (naive), `VARCHAR` |
| Boolean | `BOOLEAN` | `INTEGER`, `CHAR(1)` |
| JSON | `JSONB` | `JSON` (slower, no indexing), `TEXT` |
| Money | `NUMERIC(12,2)` | `FLOAT`, `REAL` |
| Short string | `VARCHAR` or `TEXT` (same perf in PG) | `CHAR` |
| Enum-like | `ENUM` type or `TEXT` + `CHECK` | `VARCHAR` alone |
| Array | `INTEGER[]`, `TEXT[]` | comma-separated strings |
| IP | `INET`, `CIDR` | `VARCHAR` |
| MAC | `MACADDR` | `VARCHAR` |
| Range | `INT4RANGE`, `TSTZRANGE` | two columns |

### TIMESTAMPTZ — always

`TIMESTAMP WITH TIME ZONE` stores UTC internally and converts on I/O. `TIMESTAMP` without `TZ` is a semantic trap — it has no meaning without implicit timezone assumption. For the library's portability across drivers, represent as `DATETIME(6)` in MySQL and `TIMESTAMPTZ` in PG; let the ORM layer normalize.

### UUID natively, not as CHAR(36)

```sql
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
-- or, preferred on PG 13+:
CREATE EXTENSION IF NOT EXISTS pgcrypto;

id UUID PRIMARY KEY DEFAULT gen_random_uuid()
```

16 bytes vs 36, native indexing, type-checked.

## JSONB — the right way

```sql
-- Column
metadata JSONB NOT NULL DEFAULT '{}'::JSONB

-- Querying
SELECT * FROM events WHERE metadata @> '{"source": "web"}';   -- contains
SELECT metadata->>'source' FROM events;                         -- text extraction
SELECT metadata->'tags' FROM events;                            -- jsonb extraction
SELECT * FROM events WHERE metadata ? 'source';                 -- key exists

-- GIN index for containment queries
CREATE INDEX idx_events_metadata_gin ON events USING GIN (metadata);

-- jsonb_path_ops for smaller indexes if you only need @>
CREATE INDEX idx_events_metadata_path ON events USING GIN (metadata jsonb_path_ops);
```

- `JSONB` = binary, indexed, deduplicated keys.
- `JSON` = raw text. Avoid unless you need the exact original formatting.
- Don't over-JSONify — if a field is always present and structured, it's a column. JSONB is for sparse/variant data.

## Index types — pick the right one

| Type | When |
|---|---|
| B-tree (default) | Equality, ranges, ORDER BY |
| Hash | Equality only (rare; B-tree usually better) |
| GIN | JSONB containment, array ops, full-text search |
| GiST | Geometric, exclusion constraints, ranges |
| BRIN | Very large tables with physical ordering (logs, time-series) |
| SP-GiST | Non-balanced trees — IP addresses, phone numbers |

### Partial indexes — only index what you query

```sql
-- If 99% of queries filter active users, don't waste index space on inactive ones
CREATE INDEX idx_users_active_email ON users (email) WHERE active = true;

-- For soft-delete tables
CREATE INDEX idx_posts_published ON posts (published_at) WHERE deleted_at IS NULL;
```

### Functional indexes — index an expression

```sql
-- Case-insensitive lookup
CREATE INDEX idx_users_email_lower ON users (LOWER(email));
-- Then query: WHERE LOWER(email) = LOWER(?)

-- Or use citext type instead:
CREATE EXTENSION citext;
email CITEXT UNIQUE NOT NULL
-- Then LOWER comparison is implicit
```

### Covering indexes (PG 11+) with INCLUDE

```sql
CREATE INDEX idx_orders_user_covering
  ON orders (user_id)
  INCLUDE (status, total, created_at);
-- Index-only scan possible for SELECT status, total FROM orders WHERE user_id = ?
```

## Upsert — `ON CONFLICT`

```sql
INSERT INTO users (email, name, updated_at)
VALUES (:email, :name, NOW())
ON CONFLICT (email) DO UPDATE
SET name = EXCLUDED.name,
    updated_at = NOW()
WHERE users.name IS DISTINCT FROM EXCLUDED.name  -- only update if actually different
RETURNING id, (xmax = 0) AS is_insert;
```

`RETURNING` + `xmax = 0` tells you whether it was an insert or an update. Cleaner than MySQL's `INSERT … ON DUPLICATE KEY UPDATE`.

## RETURNING — get values back on write

PG's killer feature the library should use when available:

```sql
INSERT INTO orders (...) VALUES (...) RETURNING id, created_at;
UPDATE users SET active = false WHERE last_login < :cutoff RETURNING id, email;
DELETE FROM carts WHERE created_at < :cutoff RETURNING id;
```

Saves an extra round trip vs `LAST_INSERT_ID()` or a subsequent SELECT.

## CTEs — readable complex queries

```sql
WITH recent_orders AS (
    SELECT user_id, COUNT(*) AS cnt
    FROM orders
    WHERE created_at > NOW() - INTERVAL '30 days'
    GROUP BY user_id
),
high_spenders AS (
    SELECT user_id, SUM(total) AS revenue
    FROM orders
    WHERE created_at > NOW() - INTERVAL '30 days'
    GROUP BY user_id
    HAVING SUM(total) > 500
)
SELECT u.id, u.email, ro.cnt, hs.revenue
FROM users u
JOIN recent_orders ro ON ro.user_id = u.id
JOIN high_spenders hs ON hs.user_id = u.id;
```

PG 12+ inlines CTEs by default (like subqueries), so use them for readability without a perf penalty — unless you add `MATERIALIZED` to force it.

### Recursive CTEs — tree traversal

```sql
WITH RECURSIVE tree AS (
    SELECT id, parent_id, name, 0 AS depth
    FROM categories
    WHERE parent_id IS NULL
    UNION ALL
    SELECT c.id, c.parent_id, c.name, t.depth + 1
    FROM categories c
    JOIN tree t ON c.parent_id = t.id
)
SELECT * FROM tree ORDER BY depth, name;
```

Perfect for nested categories, org charts, menu hierarchies.

## Window functions — analytics without GROUP BY

```sql
-- Top 3 most recent orders per user
SELECT *
FROM (
    SELECT
        o.*,
        ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY created_at DESC) AS rn
    FROM orders o
) ranked
WHERE rn <= 3;

-- Running total
SELECT
    order_id,
    amount,
    SUM(amount) OVER (ORDER BY created_at) AS running_total
FROM orders;

-- Percentile
SELECT
    amount,
    PERCENT_RANK() OVER (ORDER BY amount) AS pct
FROM orders;
```

## Full-text search — tsvector/tsquery

```sql
ALTER TABLE posts ADD COLUMN search_vec tsvector
  GENERATED ALWAYS AS (
    to_tsvector('english', coalesce(title, '') || ' ' || coalesce(body, ''))
  ) STORED;

CREATE INDEX idx_posts_search ON posts USING GIN (search_vec);

SELECT title, ts_rank(search_vec, query) AS rank
FROM posts, to_tsquery('english', 'postgres & performance') query
WHERE search_vec @@ query
ORDER BY rank DESC
LIMIT 20;
```

For basic needs this beats bolting on Elasticsearch.

## Row-level security (RLS) — multi-tenant isolation

```sql
ALTER TABLE posts ENABLE ROW LEVEL SECURITY;

CREATE POLICY posts_tenant_isolation ON posts
  USING (tenant_id = current_setting('app.tenant_id')::bigint);

-- Then app sets: SET app.tenant_id = '42';
-- Queries automatically filter to that tenant.
```

Powerful for SaaS: tenancy enforced at the DB level, not app level. Set `current_setting` per connection/request.

## Transactions and isolation

```sql
BEGIN;
SET TRANSACTION ISOLATION LEVEL READ COMMITTED;   -- default, fine for most
-- or: REPEATABLE READ, SERIALIZABLE for stricter
...
COMMIT;
```

- PG defaults to `READ COMMITTED`.
- `SERIALIZABLE` uses optimistic concurrency — retry on conflict is the app's job.
- `SELECT ... FOR UPDATE` for pessimistic locking within a transaction.
- `SELECT ... FOR UPDATE SKIP LOCKED` for queue-style consumers.

## EXPLAIN ANALYZE — performance tuning

```sql
EXPLAIN (ANALYZE, BUFFERS, VERBOSE)
SELECT ...;
```

What to look for:
- **`Seq Scan`** on a large table — missing index.
- **`Rows Removed by Filter: N`** where N is large — index isn't selective, or predicate can't use it.
- **`Hash Join` vs `Nested Loop`** — planner chooses based on row estimates; bad estimates = bad plan.
- **`actual rows` wildly different from `rows estimated`** — stats are stale. `ANALYZE <table>;`.
- **`shared read=N`** blocks — disk reads. High = cold cache or missing index.

Use https://explain.dalibo.com/ or `pgvis` to visualize.

### Common fixes
- Missing index: create it, ideally partial/covering if query shape allows.
- Stale statistics: run `ANALYZE <table>;` or lower `default_statistics_target` globally.
- Over-selective plan: `CREATE STATISTICS (dependencies, ndistinct) ON col1, col2 FROM t;` for cross-column correlation.
- Big `IN (?)` lists: use `ANY(ARRAY[...])` or a temp table.

## Partitioning — for very large tables

```sql
CREATE TABLE events (
    id BIGSERIAL,
    tenant_id BIGINT NOT NULL,
    occurred_at TIMESTAMPTZ NOT NULL,
    payload JSONB,
    PRIMARY KEY (occurred_at, id)
) PARTITION BY RANGE (occurred_at);

CREATE TABLE events_2026_q1 PARTITION OF events
  FOR VALUES FROM ('2026-01-01') TO ('2026-04-01');
-- ... one per quarter
```

Use partitioning when: tables > 100M rows, queries typically hit a single partition (recent data), old partitions can be dropped wholesale.

## PDO pgsql driver notes

```php
$pdo = new \PDO(
    "pgsql:host={$host};port={$port};dbname={$db}",
    $user,
    $pass,
    [
        \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES   => false,
    ],
);
// Recommended session settings:
$pdo->exec("SET TIME ZONE 'UTC'");
$pdo->exec("SET client_encoding = 'UTF8'");
```

### Gotchas
- `lastInsertId()` requires the **sequence name**, not the table name: `$pdo->lastInsertId('users_id_seq')`. Prefer `RETURNING id` instead.
- Booleans: PDO maps PHP `true`/`false` to PG `true`/`false` (not 1/0).
- Case sensitivity: unquoted identifiers are folded to lowercase. Avoid `"CamelCase"` column names — they become a quoting headache.
- JSONB values: cast explicitly `? ::jsonb` in the query or the driver treats them as strings.

## When writing driver-portable code

The library supports MySQL/PG/SQLite. For cross-driver code:
- Avoid PG-specific types (JSONB, arrays, ENUM, CIDR) or gate them behind a driver check.
- Use `BIGINT`/`BIGSERIAL`/`IDENTITY` consistently — avoid vendor-specific `AUTO_INCREMENT` syntax.
- Avoid `RETURNING` at the ORM layer (MySQL doesn't support it portably until 8.0 with different semantics). Either do a two-step insert+select, or add a driver-specific code path.
- Timestamps: `TIMESTAMPTZ` (PG) / `DATETIME(6)` (MySQL) / `TEXT` ISO-8601 (SQLite) — normalize in app layer.

## When it's OK to go native

If the library offers a "this resource targets PG only" escape hatch, use everything PG has. Don't hobble a PG-only API by emulating cross-driver constraints.

## Checklist

- [ ] TIMESTAMPTZ (not TIMESTAMP naive) for all timestamps.
- [ ] JSONB (not JSON), with GIN index if queried.
- [ ] UUID native type, not CHAR(36).
- [ ] RETURNING clause on INSERT/UPDATE/DELETE where useful.
- [ ] Partial indexes for filtered queries (e.g., `WHERE deleted_at IS NULL`).
- [ ] ANALYZE runs after bulk loads.
- [ ] EXPLAIN ANALYZE on any query seen in prod logs as slow.
- [ ] No seq scans on large tables (check via EXPLAIN).
- [ ] PDO `ATTR_EMULATE_PREPARES => false`.
- [ ] Timezone set to UTC on connect.
- [ ] Driver-portable code clearly separated from pg-only code.
