---
name: sqlite-expert
description: Expert on SQLite 3.35+ specific features and constraints — type affinity, WAL journaling, concurrency model, PRAGMAs, full-text search FTS5, JSON1, R-Tree, in-memory databases, limitations on ALTER TABLE, and PDO sqlite driver specifics. Use when targeting SQLite for embedded deployments, integration tests, or local development with the php-api-builder library.
---

# SQLite Expert — Getting the Most Out of the Smallest DB

SQLite is not a toy. It's the most widely deployed database on earth and, when its strengths align with the workload, outperforms server DBs. Use it deliberately — know its edges.

## When SQLite is the right choice

- **Local dev and tests** — zero setup, in-memory is instant.
- **Embedded apps** — mobile, IoT, desktop.
- **Single-writer workloads** — CLI tools, small APIs (< 100 writes/sec sustained).
- **Read-heavy with many concurrent readers** — WAL mode makes this shine.
- **Static/reference data** — configurations, catalogs shipped read-only.

When **not** to use SQLite:
- Multi-writer high-concurrency APIs (writes serialize globally).
- Network-accessed by multiple app servers (NFS is a trap).
- Need for fine-grained ACL or row-level security.

## Type affinity — not types

SQLite has **type affinity**, not strict types. A column declared `INTEGER` will happily store `"hello"`.

```sql
CREATE TABLE users (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    age INTEGER CHECK (age >= 0)  -- use CHECK to enforce
);

INSERT INTO users VALUES (1, 'Ada', 'not a number');  -- works! no error
```

**As of SQLite 3.37**, you can use **STRICT tables**:

```sql
CREATE TABLE users (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    age INTEGER NOT NULL CHECK (age >= 0)
) STRICT;
-- Now type violations actually error.
```

Prefer `STRICT` for new tables. Safer, more portable in spirit to other DBs.

## Declared types — map to affinities

| Declared | Affinity | Stores |
|---|---|---|
| `INTEGER`, `INT`, `BIGINT` | INTEGER | Integers 1-8 bytes |
| `TEXT`, `VARCHAR(n)`, `CLOB` | TEXT | UTF-8 / UTF-16 |
| `REAL`, `FLOAT`, `DOUBLE` | REAL | 8-byte float |
| `BLOB`, (no type) | BLOB / none | Binary |
| `NUMERIC`, `DECIMAL` | NUMERIC | Converts int-compatible values to int |

There's no native `DATETIME`, `BOOLEAN`, `UUID`. Store as:
- Timestamps: `TEXT` ISO-8601 UTC (`'2026-04-18T12:34:56.000Z'`) or `INTEGER` Unix epoch.
- Booleans: `INTEGER` 0/1.
- UUIDs: `TEXT`.
- Money: `INTEGER` cents. **Never** `REAL` (floating-point imprecision).

## WAL mode — the performance switch

```sql
PRAGMA journal_mode = WAL;
PRAGMA synchronous = NORMAL;   -- durability/speed tradeoff (FULL is default)
PRAGMA cache_size = -64000;    -- 64 MB cache (negative = KB)
PRAGMA temp_store = MEMORY;    -- temp tables in RAM
PRAGMA mmap_size = 268435456;  -- 256 MB memory-mapped I/O
PRAGMA foreign_keys = ON;      -- must be set per connection!
```

Why WAL matters:
- **Readers don't block writers, and vice versa** (in journal mode, they do).
- Writes are appended to a `-wal` file and checkpointed periodically.
- Multiple readers + one writer concurrently.

**Critical:** enable `foreign_keys = ON` on every connection. SQLite doesn't enforce FKs by default.

## PRAGMAs every connection should set

```php
$pdo = new \PDO("sqlite:{$path}", null, null, [
    \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
]);
$pdo->exec('PRAGMA foreign_keys = ON');
$pdo->exec('PRAGMA journal_mode = WAL');
$pdo->exec('PRAGMA synchronous = NORMAL');
$pdo->exec('PRAGMA busy_timeout = 5000');  // ms to wait for a lock
```

`busy_timeout` is the single biggest knob for avoiding `SQLITE_BUSY` errors in concurrent reads.

## Concurrency model — the truth

- **One writer at a time, globally.**
- Writers obtain an exclusive lock on the DB file during commit.
- Readers (in WAL mode) don't block and see a consistent snapshot.
- Transactions start as `DEFERRED` — upgrade to `IMMEDIATE` or `EXCLUSIVE` when you know a write is coming:

```sql
BEGIN IMMEDIATE;  -- acquires RESERVED lock now, prevents "database is locked" later in the txn
...
COMMIT;
```

Use `IMMEDIATE` for multi-statement transactions that include any writes.

## Full-text search — FTS5

SQLite has a built-in full-text engine. Use it instead of `LIKE '%...%'`.

```sql
CREATE VIRTUAL TABLE posts_fts USING fts5(
    title,
    body,
    content='posts',          -- external content table
    content_rowid='id',
    tokenize='porter unicode61'
);

-- Populate (or use triggers to keep in sync)
INSERT INTO posts_fts(rowid, title, body) SELECT id, title, body FROM posts;

-- Query
SELECT p.*, bm25(posts_fts) AS rank
FROM posts p
JOIN posts_fts ON posts_fts.rowid = p.id
WHERE posts_fts MATCH 'sqlite AND performance'
ORDER BY rank
LIMIT 20;
```

Operators: `AND`, `OR`, `NOT`, `"exact phrase"`, `prefix*`, `NEAR(a b, 5)`.

## JSON1 — JSON support

Built-in since 3.38. Same API as PG's JSON ops but returns text, not JSONB:

```sql
SELECT json_extract(metadata, '$.source') FROM events;
SELECT * FROM events WHERE json_extract(metadata, '$.source') = 'web';

-- Index a JSON path
CREATE INDEX idx_events_source ON events (json_extract(metadata, '$.source'));
```

## Migrations — the ALTER TABLE limitation

SQLite's `ALTER TABLE` is limited:
- ✅ `ADD COLUMN` — works.
- ✅ `RENAME COLUMN` — since 3.25.
- ✅ `DROP COLUMN` — since 3.35. Before that, requires the recreation dance.
- ❌ Change column type, add/drop constraints, change FK — not supported.

For unsupported changes, the pattern is:

```sql
BEGIN;
-- 1. Create new table
CREATE TABLE users_new (... new schema ...);

-- 2. Copy data
INSERT INTO users_new SELECT ... FROM users;

-- 3. Drop old
DROP TABLE users;

-- 4. Rename new
ALTER TABLE users_new RENAME TO users;

-- 5. Recreate indexes and triggers
CREATE INDEX ...
COMMIT;
```

Always inside a transaction.

## Indexes — same rules as other DBs

- Index every FK (SQLite does NOT auto-index FK columns!).
- Index columns in WHERE and ORDER BY.
- Partial and expression indexes work:
  ```sql
  CREATE INDEX idx_posts_published ON posts (published_at) WHERE deleted_at IS NULL;
  CREATE INDEX idx_users_email_lower ON users (LOWER(email));
  ```
- `EXPLAIN QUERY PLAN SELECT ...` to check index usage.

## In-memory databases — testing gold

```php
$pdo = new \PDO('sqlite::memory:');
```

- Completely in RAM, zero disk I/O.
- Destroyed when the connection closes.
- Perfect for integration tests — microseconds per test, zero cleanup.
- Shared in-memory across connections: `sqlite::memory:?cache=shared` + `file:memdb1?mode=memory&cache=shared`.

For the library's integration tests, use `:memory:` + the library's migration runner to set up schema per test suite.

## Backup API

SQLite exposes a streaming backup function:

```php
// Snapshot for backups, replication, or "save as"
$src = new \PDO('sqlite:/data/prod.db');
$dest = new \PDO('sqlite:/backups/backup.db');
// Library/extension needed for PDO; native SQLite C API via sqlite3 ext:
$srcConn = new \SQLite3('/data/prod.db');
$srcConn->backup(new \SQLite3('/backups/backup.db'));
```

Backups are consistent without blocking writers (WAL mode).

## R-Tree — spatial indexing

```sql
CREATE VIRTUAL TABLE places USING rtree(
    id, minLat, maxLat, minLon, maxLon
);

SELECT * FROM places WHERE minLat >= 40 AND maxLat <= 41 AND minLon >= -74 AND maxLon <= -73;
```

For geospatial lookups. Many APIs don't need this; when they do, R-Tree is faster than bounding-box predicates on a regular table.

## Gotchas

- **FKs off by default.** Enable every connection.
- **No `NOW()`** — use `CURRENT_TIMESTAMP` (UTC) or `strftime('%Y-%m-%d %H:%M:%f', 'now')` for millis.
- **`AUTOINCREMENT` keyword** is almost never needed. `INTEGER PRIMARY KEY` autoincrements by default (and faster).
- **Case sensitivity in LIKE** is off by default. `PRAGMA case_sensitive_like = ON` or use `COLLATE NOCASE`.
- **`count(*)` on big tables** is O(N). SQLite doesn't store row counts.
- **`AUTOINCREMENT` on deletes**: with it, deleted IDs are never reused (strictly monotonic). Without, IDs can be reused after DELETE.
- **Attach other DB files** with `ATTACH DATABASE` — handy for migrations or reporting across files.

## Security

- **File permissions are your security.** SQLite has no users/roles. Whoever can open the file can do anything.
- **No network protocol.** Don't expose the file over NFS/SMB — file locking is unreliable.
- **Prepared statements work the same** — use them. Never interpolate.

## PDO sqlite driver notes

```php
// File-based
$pdo = new \PDO("sqlite:{$path}");

// In-memory
$pdo = new \PDO('sqlite::memory:');

// Read-only
$pdo = new \PDO("sqlite:{$path}", null, null, [
    \PDO::SQLITE_ATTR_OPEN_FLAGS => \PDO::SQLITE_OPEN_READONLY,
]);

// lastInsertId works directly (no sequence name needed like PG)
$pdo->lastInsertId();
```

## Integration testing pattern

```php
// tests/Pest.php
beforeEach(function () {
    $pdo = new \PDO('sqlite::memory:');
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec(file_get_contents(__DIR__ . '/Fixtures/schema.sqlite.sql'));
    Connection::setInstance($pdo);
});
```

Sub-100ms per test, zero leakage between tests, no Docker needed.

## Driver-portable considerations

If writing code that must work on SQLite + MySQL + PG:
- No `RETURNING` (SQLite 3.35+ supports it but old versions don't). Prefer `lastInsertId()` + SELECT.
- No JSONB — SQLite has JSON1 (text-based); MySQL has JSON; PG has JSONB. Pick the lowest common denominator (JSON as TEXT, parse in app) or gate per driver.
- Store booleans as `INTEGER` 0/1 — then in app layer coerce to `bool`.
- Timestamps as `TEXT` ISO-8601 UTC — most portable representation.

## Checklist

- [ ] `PRAGMA foreign_keys = ON` set per connection.
- [ ] `PRAGMA journal_mode = WAL` for production/multi-reader scenarios.
- [ ] `PRAGMA busy_timeout` to absorb contention.
- [ ] `STRICT` tables for new schemas where portable.
- [ ] Indexes on FK columns (SQLite doesn't auto-add them).
- [ ] `BEGIN IMMEDIATE` for write transactions.
- [ ] In-memory + WAL mode for the test suite.
- [ ] No floating-point storage of money.
- [ ] FTS5 instead of `LIKE '%...%'` for search.
- [ ] File permissions set appropriately for prod deployments.
