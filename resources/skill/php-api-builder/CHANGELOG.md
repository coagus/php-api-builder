# Skill Changelog — php-api-builder

Format: [Keep a Changelog](https://keepachangelog.com/) and [SemVer](https://semver.org/).

This log tracks changes to the library-shipped skill at `resources/skill/php-api-builder/SKILL.md`, consumed by Claude agents building APIs with `coagus/php-api-builder` v2.

## [2.2.0] - 2026-04-18

Backward-compatible additions reflecting library changes shipped in `v2.0.0-alpha.23`.

### Added

- New top-level section **Well-known routes (RFC 8615)** covering the `wellKnown` argument on `API::__construct()` — maps `/.well-known/*` paths to `[Class, method]` tuples so JWKS, OpenID Connect discovery, OAuth 2.0 authorization-server metadata, and `security.txt` can be served outside `$apiPrefix`.
- New reference file `references/well-known-routes.md` with the canonical handler, fail-fast validation rules, RFC 7517 JWKS envelope variants (`data`-wrapped vs plain `{"keys": [...]}` with `application/jwk-set+json`), dispatch semantics (middleware implications), and a table of common discovery endpoints.

### Changed

- Skill description now triggers on: `well-known routes`, `/.well-known/ path`, `JWKS`, `jwks.json`, `OpenID Connect discovery`, `openid-configuration`, `OAuth authorization server metadata`, `security.txt`, `RFC 8615`, `wellKnown argument`, `API constructor`. Downstream agents asking about any of these now auto-load the skill.

### Fixed

- No fixes; additive release.

### Notes on skill size

SKILL.md grew from 799 → 818 lines (target 800, hard cap 1200 per `.claude/skills/skill-curator/SKILL.md`). The detailed content lives in `references/well-known-routes.md`, keeping the SKILL entry at the "compact pattern + pointer" level.

## [2.1.0] - 2026-04-18

Backward-compatible additions reflecting library changes shipped in `v2.0.0-alpha.22`.

### Added

- Quick reference entry for `#[Ignore]` (property) — marks a public property as invisible to ORM, validator, and OpenAPI. Used for virtual property hooks that transform input without persisting the raw value (e.g. `set =>` password hashers writing to a sibling `#[Hidden] passwordHash` column).
- New section **Virtual Property Hooks with `#[Ignore]`** under "Creating an Entity" showing the canonical hook + backing-column pattern.
- New subsection **Foreign-key column name is idempotent on `_id`** documenting that `#[BelongsTo]` inference no longer doubles the `_id` suffix when the PHP property already ends in `Id` / `_id`.
- `#[Middleware]` quick-reference row updated to `#[Middleware(Class, ...args)]` signaling parameterized + repeatable construction.
- Expanded **Middleware** section describing the four-layer dispatch pipeline (globals → class-level → method-level → handler) with a stacked example and a `TenantGuard` sample middleware carrying constructor arguments via named attribute args.

### Changed

- **Per-resource rate-limiting example rewritten.** Previously the skill showed `#[Middleware(new RateLimitMiddleware(limit: 20))]`, which is invalid PHP — attribute arguments must be constant expressions. The pattern is now `#[Middleware(RateLimitMiddleware::class, limit: 20, windowSeconds: 60)]`, which the library's `MiddlewareResolver` instantiates at dispatch time.
- **Custom-middleware example placement corrected.** The sample no longer attaches middleware directly to an `Entity` subclass; it attaches to an `APIDB` subclass at class level and to a specific verb at method level, matching the real dispatch model.

### Fixed

- Triggers for `per-route middleware`, `#[Middleware] attribute`, `#[Ignore] attribute`, `virtual property hook`, `password hash hook`, `set-only hook`, `foreign key idempotent`, and `FK column suffix` added to the skill description so downstream agents auto-load when asked about any of these.

## [2.0.0] - 2026-04-18

First versioned release of the skill. Previous revisions were unversioned and tracked library v1.x behavior. This entry captures every divergence between v1 and v2 that a consuming agent must know.

### BREAKING CHANGES

- **`Entity::save()` throws `ValidationException`, not `RuntimeException`.** The new exception carries `public readonly array $errors` keyed by field name. Agents catching `\RuntimeException` for validation errors must migrate to `Coagus\PhpApiBuilder\Exceptions\ValidationException` and read `$e->errors`.
- **`ErrorHandler` dispatches on exception class, not message contents.** To produce a 404, code must throw `Coagus\PhpApiBuilder\Exceptions\EntityNotFoundException`. Matching "not found" in an exception message no longer yields 404 — it yields 500.
- **`CorsMiddleware` rejects `CORS_ALLOWED_ORIGINS=*` combined with `CORS_ALLOW_CREDENTIALS=true`** at construction time with `InvalidArgumentException`. Previously this misconfiguration was silent and produced non-compliant responses.
- **`DriverInterface` gains three required methods**: `getCurrentTimestampExpression(): string`, `applySessionSettings(\PDO): void`, `getRefreshTokenTableDdl(): string`. Custom driver implementations from v1 will not load until they implement them.
- **Error responses use `Content-Type: application/problem+json; charset=utf-8`** per RFC 7807. v1 emitted `application/json`. Clients that dispatch response handling on the `Content-Type` header must accept both `application/json` (success) and `application/problem+json` (errors).

### Added

- `Coagus\PhpApiBuilder\Exceptions\ValidationException` (final) — thrown by `Entity::save()`; exposes `public readonly array $errors`.
- `Coagus\PhpApiBuilder\Exceptions\EntityNotFoundException` (final) — throw this to produce a 404 response.
- `CORS_ALLOW_CREDENTIALS` env var (default `false`) — controls the `Access-Control-Allow-Credentials` header.
- `RATE_LIMIT_TRUSTED_PROXIES` env var (comma-separated IPs, default empty) — list of proxy IPs allowed to set `X-Forwarded-For` for rate-limit keying.
- `QueryBuilder::getColumnAllowlist(): list<string>` — public API; `APIDB` uses it to filter `?sort=` and `?fields=` against the entity's actual column set. Overridable per entity.
- `docs:generate --namespace=App` — scan a custom root namespace when exporting the OpenAPI spec (default `App`).
- Driver session settings applied automatically on connect:
  - SQLite: `PRAGMA foreign_keys=ON`, `PRAGMA journal_mode=WAL`, `PRAGMA busy_timeout=5000`.
  - MySQL: `SET NAMES utf8mb4`, `SET time_zone='+00:00'`.
  - Postgres: `SET TIME ZONE 'UTC'`, `SET client_encoding='UTF8'`.

### Security

- `?sort=` and `?fields=` identifiers are filtered against the column allowlist — SQL-injection via identifier parameters is no longer possible. A request like `?sort=id;DROP TABLE users--` is normalized to `ORDER BY id ASC`.
- `X-Forwarded-For` is honored only when `REMOTE_ADDR` is in `RATE_LIMIT_TRUSTED_PROXIES`. Otherwise raw `REMOTE_ADDR` is used, preventing client IP spoofing.
- `php api serve` invokes PHP via `proc_open` with an argv array (no shell) — command-line arguments are not subject to shell expansion.
- JWT validation now unconditionally verifies `iss`, `aud`, `sub`, and `jti` claims. Tokens missing any of these are rejected.
- Swagger UI / ReDoc HTML output is escaped to prevent XSS via spec metadata.
- CORS wildcard + `Access-Control-Allow-Credentials: true` is rejected per W3C spec.

### Fixed

- `Entity::delete()` is now portable across MySQL, Postgres, and SQLite — soft-delete uses the driver-provided current-timestamp expression instead of a hardcoded `NOW()`.
- `QueryBuilder::orWhere()` no longer emits malformed SQL when called as the first clause in a chain.
- Soft-delete filter is parenthesized when combined with `orWhere()`; previously `orWhere` could bypass the `deleted_at IS NULL` predicate.
- `ErrorHandler` no longer leaks raw exception messages in production for unmapped `\Throwable`s — returns a generic "Internal Server Error" and logs the original.
- `env:check` reports the correct minimum PHP version (8.4), matching `composer.json`.
- `docs:generate` correctly registers both entities and services in the emitted OpenAPI spec; previously services were silently skipped.

### Removed

- **Phantom CLI commands** that never existed in the library are removed from the skill:
  - `php api test`
  - `php api test --coverage`
  - `php api skill:install` (skill installation happens automatically during `php api init`)
- **Incorrect claim that `make:entity` / `make:service` generate tests.** They generate the resource file only.

---

## Migration Guide: v1.x → v2.0.0

### 1. Catch `ValidationException`, not `RuntimeException`

Before (v1):
```php
try {
    $product->save();
} catch (\RuntimeException $e) {
    $errors = json_decode($e->getMessage(), true);
    return $this->error('Validation failed', 422, ['errors' => $errors]);
}
```

After (v2):
```php
use Coagus\PhpApiBuilder\Exceptions\ValidationException;

try {
    $product->save();
} catch (ValidationException $e) {
    return $this->error($e->getMessage(), 422, ['errors' => $e->errors]);
}
```

Or simply let it propagate — `ErrorHandler` will emit the correct 422 RFC 7807 response with the `errors` map embedded.

### 2. Throw `EntityNotFoundException` for 404s

Before (v1):
```php
$user = User::find($id);
if (!$user) {
    throw new \RuntimeException('User not found');   // matched by message → 404
}
```

After (v2):
```php
use Coagus\PhpApiBuilder\Exceptions\EntityNotFoundException;

$user = User::find($id);
if ($user === null) {
    throw new EntityNotFoundException("User {$id} not found");
}
```

A plain `\RuntimeException` with "not found" in the message now produces a 500, not a 404.

### 3. Fix CORS misconfiguration before deploying

Before (v1 — silently broken):
```env
CORS_ALLOWED_ORIGINS=*
CORS_ALLOW_CREDENTIALS=true
```

After (v2 — throws at startup):
```env
CORS_ALLOWED_ORIGINS=https://app.example.com,https://admin.example.com
CORS_ALLOW_CREDENTIALS=true
```

If wildcard is required, keep credentials off:
```env
CORS_ALLOWED_ORIGINS=*
CORS_ALLOW_CREDENTIALS=false
```

### 4. Update custom `DriverInterface` implementations

Add the three new methods:
```php
public function getCurrentTimestampExpression(): string
{
    return 'CURRENT_TIMESTAMP';   // or 'NOW()' / "datetime('now')"
}

public function applySessionSettings(\PDO $pdo): void
{
    // per-connection SET NAMES, PRAGMA, etc.
}

public function getRefreshTokenTableDdl(): string
{
    return 'CREATE TABLE IF NOT EXISTS refresh_tokens (…)';
}
```

### 5. Handle `application/problem+json` on the client

If you parse error bodies based on `Content-Type`:

```js
// Before: matched 'application/json' on errors
// After: accept both
const ct = res.headers.get('content-type') ?? '';
if (ct.startsWith('application/json') || ct.startsWith('application/problem+json')) {
    const body = await res.json();
    // body always has { type, title, status, detail, requestId } on errors
}
```

### 6. Remove calls to phantom CLI commands

Scripts, CI pipelines, or docs that invoke any of these will fail silently or loudly:

- `php api test` — never existed. Use `vendor/bin/pest` or your test runner directly.
- `php api test --coverage` — same.
- `php api skill:install` — never existed. The skill is installed by `php api init`.

### 7. Configure trusted proxies if you rate-limit behind a load balancer

If your API sits behind a reverse proxy or load balancer, set:
```env
RATE_LIMIT_TRUSTED_PROXIES=10.0.0.1,10.0.0.2
```

Otherwise leave it empty — setting it without a real proxy in front lets clients spoof their IP.
