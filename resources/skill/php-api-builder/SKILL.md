---
name: php-api-builder
description: "Development assistant for building RESTful APIs with the coagus/php-api-builder v2 library. Use this skill whenever the user mentions php-api-builder, wants to create PHP API entities, services, middleware, authentication with JWT, query building, or any task related to building a REST API using this Composer package. Also trigger when the user asks about creating CRUD endpoints, defining database entities with PHP attributes, configuring API routing, or setting up ORM relationships (BelongsTo, HasMany, BelongsToMany). Trigger on: validation exception, entity not found exception, driver portability, RFC 7807 problem json, column allowlist, trusted proxies, CORS credentials, application/problem+json, ValidationException, EntityNotFoundException, per-route middleware, #[Middleware] attribute, #[Ignore] attribute, virtual property hook, password hash hook, set-only hook, foreign key idempotent, FK column suffix. This skill knows every pattern, attribute, and convention of the library."
---

# PHP API Builder v2 - Development Skill

This skill helps developers build RESTful APIs using `coagus/php-api-builder` v2. It knows the library's architecture, conventions, and every available feature so developers can use the library to its full potential.

## Core Architecture

The library follows an **Active Record** pattern with **PHP 8.4** features (property hooks, asymmetric visibility, typed properties). There are two types of resources:

- **Entities** (extend `Entity`) - Mapped to database tables, get automatic CRUD via `APIDB`
- **Services** (extend `Service`) - Pure logic, no database dependency (external APIs, health checks, gateways)
- **Hybrid** - A Service that also uses APIDB internally for custom + CRUD endpoints

Both inherit from `Resource`, which provides response methods and request helpers.

## Quick Reference - PHP Attributes

| Attribute | Target | Purpose |
|-----------|--------|---------|
| `#[Table('name')]` | Class | Maps entity to DB table |
| `#[PrimaryKey]` | Property | Marks auto-increment primary key |
| `#[Required]` | Property | Field must be present on create/update |
| `#[Email]` | Property | Validates email format |
| `#[Unique]` | Property | Ensures uniqueness in DB |
| `#[MaxLength(n)]` | Property | Maximum string length |
| `#[MinLength(n)]` | Property | Minimum string length |
| `#[Hidden]` | Property | Excluded from JSON output and create schema (e.g., password hashes) |
| `#[IsReadOnly]` | Property | Excluded from create/update schema (auto-generated fields like timestamps, slugs) |
| `#[Ignore]` | Property | Invisible to ORM, validator, and OpenAPI — used for virtual property hooks that transform input |
| `#[SoftDelete]` | Class | Enables soft delete (deleted_at column) |
| `#[BelongsTo(Class)]` | Property | Many-to-one relationship |
| `#[HasMany(Class)]` | Property | One-to-many relationship |
| `#[BelongsToMany(Class)]` | Property | Many-to-many (pivot table) |
| `#[PublicResource]` | Class | No auth required for this resource |
| `#[Route('path')]` | Class | Custom URL path override |
| `#[Middleware(Class, ...args)]` | Class/Method | Attach middleware per-route with parameterized construction; repeatable |

## Creating an Entity

When the user wants to create a new entity, generate it following this pattern:

```php
<?php

namespace App\Entities;

use Coagus\PhpApiBuilder\ORM\Entity;
use Coagus\PhpApiBuilder\Attributes\{Table, PrimaryKey, SoftDelete};
use Coagus\PhpApiBuilder\Validation\Attributes\{Required, Email, MaxLength, MinLength, Unique, Hidden, IsReadOnly};
use Coagus\PhpApiBuilder\Attributes\{BelongsTo, HasMany};

#[Table('products')]
#[SoftDelete]
class Product extends Entity
{
    #[PrimaryKey]
    public private(set) int $id;               // asymmetric visibility: read public, write private

    #[Required, MaxLength(100)]
    public string $name {
        set => trim($value);                    // property hook: auto-trim
    }

    #[Required]
    public string $description;

    #[Required]
    public float $price {
        set {
            if ($value < 0) throw new \InvalidArgumentException('Price must be positive');
            $this->price = round($value, 2);
        }
    }

    public bool $active = true;                 // default value

    #[BelongsTo(Category::class)]
    public int $categoryId;                     // FK — included in responses AND create schema

    #[HasMany(Review::class)]
    public array $reviews;                      // loaded via eager loading, not a DB column

    #[IsReadOnly]
    public string $createdAt;                   // excluded from create schema, set by beforeCreate()
}
```

Key points to explain to the developer:
- `public private(set)` on `$id` means anyone can read it but only the entity itself (via DB) can set it
- Property hooks (`set =>`) run automatically on assignment - use for sanitization and validation
- `#[SoftDelete]` adds a `deleted_at` column; `delete()` sets it instead of removing the row
- `#[IsReadOnly]` marks fields that are auto-generated (timestamps, slugs) — they appear in GET responses but not in POST/PUT schemas in Swagger
- `#[Hidden]` completely excludes fields from all JSON output (responses and schemas) — use for persisted password hashes
- `#[Ignore]` removes a property from ORM, validator, and schemas entirely — use when the property is a write-only virtual hook (see "Virtual Property Hooks" below)
- `#[BelongsTo]` FK properties (like `$categoryId`) are always included in responses and create schemas — they are the foreign key the user sends
- Relationship properties (`$reviews`) are not DB columns; they're populated via eager loading with `->with('reviews')`
- Default values (like `$active = true`) are used when the field is not provided

### Foreign-key column name is idempotent on `_id`

`#[BelongsTo(Class)]` without an explicit `foreignKey:` infers the DB column from the PHP property name. The inference is **idempotent** on the `_id` suffix:

- `public int $categoryId` → column `category_id`
- `public int $category_id` → column `category_id` (no double suffix)
- `public TestRole $role` (typed) → column `role_id`

Never name a property `xxxIdId` expecting special handling — the helper `Utils::foreignKeyColumn()` appends `_id` only when the snake_cased name does not already end in `_id`.

### Virtual Property Hooks with `#[Ignore]`

PHP 8.4 set-only hooks let you transform input without persisting the raw value. Pair them with `#[Ignore]` so the ORM ignores the virtual property and writes only the backing column:

```php
use Coagus\PhpApiBuilder\Attributes\Ignore;
use Coagus\PhpApiBuilder\Validation\Attributes\Hidden;

class User extends Entity
{
    #[Hidden]
    public string $passwordHash = '';          // the real DB column

    #[Ignore]
    public string $password {                  // the virtual hook
        set => $this->passwordHash = password_hash($value, PASSWORD_ARGON2ID);
    }
}

// Usage
$user = new User();
$user->password = $request->input->password;   // hashes into passwordHash
$user->save();                                 // INSERT persists password_hash only
```

An `#[Ignore]` property:
- is NOT written to INSERT / UPDATE
- is NOT hydrated from SELECT rows (avoids re-hashing on load)
- is NOT checked by the validator (avoids triggering the set hook with a read)
- is NOT emitted in response bodies
- is NOT included in the sort/fields allowlist or OpenAPI schema

Without `#[Ignore]`, the ORM's reflection pass would re-invoke the `set` hook during hydration, corrupting the already-hashed value. Use `#[Ignore]` whenever the property exists only to transform incoming data.

### Validation failures throw `ValidationException`

`Entity::save()` throws `Coagus\PhpApiBuilder\Exceptions\ValidationException` when any validation attribute fails. The exception carries a `public readonly array $errors` keyed by field name:

```php
use Coagus\PhpApiBuilder\Exceptions\ValidationException;

try {
    $product = new Product();
    $product->fill($input);
    $product->save();
} catch (ValidationException $e) {
    // $e->errors has the exact shape:
    // [
    //     'email' => ['Email is not a valid address'],
    //     'name'  => ['Name is required', 'Name exceeds max length 100'],
    // ]
    $this->error($e->getMessage(), 422, ['errors' => $e->errors]);
}
```

If you let the exception propagate, `ErrorHandler` emits a `422 Unprocessable Entity` in RFC 7807 format with the `errors` map embedded. Never catch `\RuntimeException` to detect validation failures — that was the v1 behavior and no longer applies.

## Creating a Service

Services are for endpoints that don't map directly to a DB table:

```php
<?php

namespace App\Services;

use Coagus\PhpApiBuilder\Resource\Service;
use Coagus\PhpApiBuilder\Attributes\{PublicResource, Route};

#[PublicResource]
#[Route('health')]
class Health extends Service
{
    public function get(): void
    {
        $this->success([
            'status' => 'healthy',
            'timestamp' => date('c'),
            'version' => '2.0.0',
        ]);
    }
}
```

Services can also call external APIs, perform calculations, or act as gateways. They have access to all `Resource` methods but no ORM.

## Creating a Hybrid Resource

When a resource needs CRUD plus custom endpoints:

```php
<?php

namespace App\Services;

use Coagus\PhpApiBuilder\Resource\APIDB;
use App\Entities\User;

class UserService extends APIDB
{
    protected string $entity = User::class;

    // CRUD auto: GET, POST, PUT, PATCH, DELETE already work

    // Custom endpoint: POST /api/v1/users/login
    public function postLogin(): void
    {
        $input = $this->getInput();
        $user = User::query()->where('email', $input->email)->first();

        if (!$user || !password_verify($input->password, $user->password)) {
            $this->error('Invalid credentials', 401);
            return;
        }

        $token = Auth::generateAccessToken($user->toArray());
        $this->success(['token' => $token]);
    }

    // Custom endpoint: POST /api/v1/users/activate
    public function postActivate(): void
    {
        // activation logic...
        $this->noContent();
    }
}
```

Method naming convention for custom endpoints: `{httpMethod}{Action}` -> maps to `{METHOD} /api/v1/{resource}/{action}`

## Query Builder - 5 Levels

When the developer needs to query data, guide them through the appropriate level:

### Level 1 - Shortcuts (simple lookups)
```php
$user = User::find(1);                          // by ID
$users = User::all();                           // all records
$user = User::query()->where('email', $email)->first();  // first match
```

### Level 2 - Fluent Builder (filtering, sorting, pagination)
```php
$users = User::query()
    ->where('active', true)
    ->where('role_id', 2)
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->offset(20)
    ->get();
```

### Level 3 - Eager Loading (relationships, avoid N+1)
```php
$users = User::query()
    ->with('orders', 'orders.items')            // nested eager loading
    ->where('active', true)
    ->get();
// $users[0]->orders is already loaded, no extra queries
```

### Level 4 - Scopes (reusable query fragments)
```php
// In Entity definition:
public static function scopeActive(QueryBuilder $query): QueryBuilder
{
    return $query->where('active', true);
}

public static function scopeRecent(QueryBuilder $query, int $days = 30): QueryBuilder
{
    return $query->where('created_at', '>=', date('Y-m-d', strtotime("-{$days} days")));
}

// Usage:
$users = User::query()->active()->recent(7)->get();
```

### Level 5 - Raw SQL (escape hatch, always parameterized)
```php
$results = Connection::getInstance()->query(
    'SELECT u.*, COUNT(o.id) as order_count FROM users u LEFT JOIN orders o ON o.user_id = u.id GROUP BY u.id HAVING order_count > ?',
    [5]
);
```

Always use parameterized queries (`?` placeholders) - NEVER string interpolation.

## Response Methods

All resources inherit these from `Resource`:

```php
$this->success($data, 200);        // 200 with data
$this->created($data);             // 201 Created
$this->noContent();                // 204 No Content
$this->error('message', 400);      // Error with RFC 7807 format
$this->getInput();                 // Parsed request body as object
$this->getQueryParams();           // URL query parameters as array
```

APIDB auto-wraps responses: GET list returns paginated format, GET by ID returns single resource, POST returns 201, DELETE returns 204.

Success responses use `Content-Type: application/json; charset=utf-8`. Error responses (4xx/5xx) use `Content-Type: application/problem+json; charset=utf-8` per RFC 7807. Clients that dispatch on Content-Type must handle both.

## JSON Response Formats

All JSON keys use **lowerCamelCase** (`userId`, `createdAt`, `totalPages`). URL query parameters use **snake_case** (`?per_page=20&sort_by=name`). The `fill()` method accepts both formats as input.

### Success (single)
```json
{
    "data": { "id": 1, "name": "Carlos", "email": "carlos@test.com", "roleId": 2, "createdAt": "2026-01-15 10:30:00" }
}
```

### Success (list with pagination)
```json
{
    "data": [...],
    "meta": {
        "currentPage": 1,
        "perPage": 20,
        "total": 150,
        "totalPages": 8
    }
}
```

### Error (RFC 7807)
```json
{
    "type": "https://api.example.com/errors/validation",
    "title": "Validation Error",
    "status": 422,
    "detail": "The field 'email' is not a valid email address",
    "requestId": "a3f4b2c1e9d80716"
}
```

## Error Handling

`ErrorHandler` dispatches on exception class (not on message contents). To produce a specific HTTP status, throw the matching exception class:

| Throw | HTTP status | Intended use |
|-------|-------------|--------------|
| `Coagus\PhpApiBuilder\Exceptions\EntityNotFoundException` | 404 | Resource lookup miss |
| `Coagus\PhpApiBuilder\Exceptions\ValidationException` | 422 | Validation failure (auto-thrown by `Entity::save()`) |
| `Coagus\PhpApiBuilder\Exceptions\AuthenticationException` | 401 | Missing/invalid credentials |
| `Coagus\PhpApiBuilder\Exceptions\AuthorizationException` | 403 | Authenticated but lacks permission |
| `Coagus\PhpApiBuilder\Exceptions\RateLimitException` | 429 | Rate limit exceeded |
| any other `\Throwable` | 500 | Unhandled — message is hidden in production |

```php
use Coagus\PhpApiBuilder\Exceptions\{EntityNotFoundException, ValidationException};

public function getShow(int $id): void
{
    $user = User::find($id);
    if ($user === null) {
        throw new EntityNotFoundException("User {$id} not found");
    }
    $this->success($user->toArray());
}
```

Matching "not found" in an exception message no longer produces a 404 — that was v1 behavior. Always throw `EntityNotFoundException`. In production, raw exception messages from unmapped `\Throwable`s are replaced with a generic "Internal Server Error"; the original message is logged but never leaked to clients.

## Naming Conventions

| Context | Convention | Example |
|---------|-----------|---------|
| URL paths | kebab-case | `/api/v1/user-profiles` |
| URL query params | snake_case | `?per_page=20&sort_by=name` |
| JSON keys | lowerCamelCase | `{ "firstName": "Carlos" }` |
| PHP classes | PascalCase | `UserProfile` |
| PHP methods | camelCase | `getActiveUsers()` |
| PHP properties | camelCase | `$firstName` |
| DB tables | snake_case plural | `user_profiles` |
| DB columns | snake_case | `first_name` |

The library auto-converts between these conventions (e.g., DB `first_name` <-> JSON `firstName`).

## Entity Lifecycle Hooks

Entities support hooks that execute at specific points in the lifecycle:

```php
#[Table('users')]
class User extends Entity
{
    // ... properties ...

    protected function beforeCreate(): void
    {
        $this->createdAt = date('Y-m-d H:i:s');
    }

    protected function afterCreate(): void
    {
        // Send welcome email, log event, etc.
    }

    protected function beforeUpdate(): void
    {
        $this->updatedAt = date('Y-m-d H:i:s');
    }

    protected function beforeDelete(): void
    {
        // Check for dependent records, clean up relations
    }
}
```

Available hooks: `beforeCreate`, `afterCreate`, `beforeUpdate`, `afterUpdate`, `beforeDelete`, `afterDelete`.

## Authentication Setup

The library uses JWT with OAuth 2.1 practices:

```php
// .env configuration
JWT_SECRET=your-secret-key
JWT_ALGORITHM=RS256          // or ES256, HS256
JWT_ACCESS_TTL=900           // 15 minutes in seconds
JWT_REFRESH_TTL=604800       // 7 days in seconds
```

Protected resources (default) require a valid JWT token. Use `#[PublicResource]` to make endpoints public. Scopes control fine-grained access.

The JWT validator unconditionally verifies `iss`, `aud`, `sub`, and `jti` claims on every request. Tokens missing any of these are rejected with `401 Unauthorized`. Configure the expected issuer/audience via `JWT_ISSUER` and `JWT_AUDIENCE` env vars.

### API Key Authentication

As an alternative to JWT, the library supports API key authentication via the `ApiKeyAuth` class:

```php
use Coagus\PhpApiBuilder\Auth\ApiKeyAuth;

// Option 1: Use default header (X-API-Key) and validate against API_KEY env var
ApiKeyAuth::configure();

// Option 2: Custom header name and validator
ApiKeyAuth::configure('X-Custom-Key', function (string $key): bool {
    return $key === 'my-secret-api-key';
});
```

When configured, `AuthMiddleware` checks API key first, then falls back to JWT Bearer token.

## Rate Limiting

Built-in rate limiting middleware with file-based storage (no external dependencies):

```php
// In index.php — global rate limit
$api->middleware([
    new RateLimitMiddleware(limit: 100, windowSeconds: 60),
    CorsMiddleware::class,
    SecurityHeadersMiddleware::class,
]);
```

Or via environment variables (no constructor args needed):
```env
RATE_LIMIT_MAX=100
RATE_LIMIT_WINDOW=60
RATE_LIMIT_TRUSTED_PROXIES=10.0.0.1,10.0.0.2   # comma-separated, default empty
```

### Trusted proxy boundary for `X-Forwarded-For`

Rate limiting keys by client IP. `X-Forwarded-For` is only honored when `REMOTE_ADDR` is in `RATE_LIMIT_TRUSTED_PROXIES`; otherwise the raw `REMOTE_ADDR` is used. Leave `RATE_LIMIT_TRUSTED_PROXIES` empty (the default) unless you run behind a reverse proxy or load balancer — setting it without a real proxy in front lets clients spoof their IP.

Per-resource or per-method rate limiting using the `#[Middleware]` attribute. Arguments on the attribute are forwarded to the middleware constructor as named arguments:

```php
use Coagus\PhpApiBuilder\Attributes\Middleware;
use Coagus\PhpApiBuilder\Http\Middleware\RateLimitMiddleware;

// Class-level: applies to every verb on this resource.
#[Middleware(RateLimitMiddleware::class, limit: 20, windowSeconds: 60)]
class PostService extends APIDB
{
    protected string $entity = Post::class;

    // Method-level override: tighter budget for the expensive custom endpoint.
    #[Middleware(RateLimitMiddleware::class, limit: 3, windowSeconds: 60)]
    public function postExport(): void { /* ... */ }
}
```

Responses include standard headers:
```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 97
X-RateLimit-Reset: 1680000060
```

When exceeded, returns `429 Too Many Requests` in RFC 7807 format with a `Retry-After` header.

Custom storage can be injected via the `store` parameter:
```php
new RateLimitMiddleware(limit: 100, store: new RateLimitStore('/path/to/storage'))
```

## CORS

CORS is handled by `CorsMiddleware`, configured via `.env`:

```env
CORS_ALLOWED_ORIGINS=https://app.example.com,https://admin.example.com
CORS_ALLOWED_METHODS=GET,POST,PUT,PATCH,DELETE,OPTIONS
CORS_ALLOWED_HEADERS=Content-Type,Authorization,X-Requested-With
CORS_MAX_AGE=86400
CORS_ALLOW_CREDENTIALS=false    # default false
```

### Credentials + wildcard is rejected at construction

Setting `CORS_ALLOWED_ORIGINS=*` together with `CORS_ALLOW_CREDENTIALS=true` throws `InvalidArgumentException` when `CorsMiddleware` is built. The W3C CORS spec forbids this combination, and the library fails fast instead of silently mis-serving. When credentials are required, list exact origins:

```env
CORS_ALLOWED_ORIGINS=https://app.example.com
CORS_ALLOW_CREDENTIALS=true
```

## Middleware

The library dispatches middleware in four layers on every matched request:

1. **Globals** registered via `API::middleware([...])` — CORS, security headers, auth, rate limit, etc.
2. **Class-level** `#[Middleware]` attributes on the resource class (`Service`, `APIDB`, or `Resource` subclass).
3. **Method-level** `#[Middleware]` attributes on the specific verb handler (`get`, `post`, `postLogin`, ...).
4. **Handler** — the verb method itself.

Globals set the baseline; per-route `#[Middleware]` adds targeted policy. The attribute is repeatable at both class and method scope and runs attributes in declaration order.

### Creating a custom middleware

```php
<?php

namespace App\Middleware;

use Coagus\PhpApiBuilder\Helpers\ApiResponse;
use Coagus\PhpApiBuilder\Http\Middleware\MiddlewareInterface;
use Coagus\PhpApiBuilder\Http\Request;
use Coagus\PhpApiBuilder\Http\Response;

final class TenantGuard implements MiddlewareInterface
{
    public function __construct(private readonly string $header = 'X-Tenant-Id') {}

    public function handle(Request $request, callable $next): Response
    {
        if ($request->getHeader($this->header) === null) {
            return ApiResponse::error('Missing tenant', 400);
        }

        return $next($request);
    }
}
```

### Attaching it per-route

```php
use App\Middleware\TenantGuard;
use Coagus\PhpApiBuilder\Attributes\Middleware;
use Coagus\PhpApiBuilder\Http\Middleware\RateLimitMiddleware;

// Class-level: every verb on Orders requires the tenant header.
#[Middleware(TenantGuard::class, header: 'X-Tenant-Id')]
class Orders extends APIDB
{
    protected string $entity = Order::class;

    // Method-level: stack multiple attributes in declaration order.
    #[Middleware(RateLimitMiddleware::class, limit: 5, windowSeconds: 60)]
    public function postExport(): void { /* ... */ }
}
```

`#[Middleware]` must target a class that implements `MiddlewareInterface`; otherwise the dispatcher raises `RuntimeException` at resolution time — it never silently drops the declaration. Middleware for `Service` / `APIDB` subclasses is honored; a bare `Entity` gets wrapped in a generic `APIDB` at dispatch and its per-route attributes are picked up from there.

## Transactions

For operations that need atomic consistency:

```php
use Coagus\PhpApiBuilder\ORM\Connection;

Connection::transaction(function () {
    $order = new Order();
    $order->userId = $userId;
    $order->total = $total;
    $order->save();

    foreach ($items as $item) {
        $orderItem = new OrderItem();
        $orderItem->orderId = $order->id;
        $orderItem->productId = $item['productId'];
        $orderItem->quantity = $item['quantity'];
        $orderItem->save();
    }

    // If any save() fails, everything rolls back
});
```

## Database Drivers

The library supports MySQL, PostgreSQL, and SQLite via PDO:

```php
// .env
DB_DRIVER=mysql              // mysql | pgsql | sqlite
DB_HOST=localhost
DB_PORT=3306
DB_NAME=my_database
DB_USER=root
DB_PASSWORD=secret

// SQLite only needs:
DB_DRIVER=sqlite
DB_DATABASE=/path/to/database.sqlite
```

### Automatic session settings per driver

Each driver applies portable session settings on connect via `DriverInterface::applySessionSettings(PDO)`. Assume these are always active — do not re-issue them manually:

| Driver | Settings applied on connect |
|--------|----------------------------|
| `sqlite` | `PRAGMA foreign_keys=ON`, `PRAGMA journal_mode=WAL`, `PRAGMA busy_timeout=5000` |
| `mysql` | `SET NAMES utf8mb4`, `SET time_zone='+00:00'` |
| `pgsql` | `SET TIME ZONE 'UTC'`, `SET client_encoding='UTF8'` |

Timestamps are always stored in UTC. `Entity::delete()` and soft-delete use the driver-provided `getCurrentTimestampExpression()` (e.g. `CURRENT_TIMESTAMP` / `NOW()` / `datetime('now')`), so `delete()` works identically across all three drivers.

### Writing a custom driver

`DriverInterface` requires these methods (added in v2):

```php
public function getCurrentTimestampExpression(): string;   // e.g. "CURRENT_TIMESTAMP"
public function applySessionSettings(\PDO $pdo): void;     // called once after connect
public function getRefreshTokenTableDdl(): string;          // CREATE TABLE … for refresh_tokens
```

Custom driver implementations from v1 must add these three methods to remain compatible.

## URL Filtering, Sorting, and Pagination

APIDB endpoints automatically support:

```
GET /api/v1/users?page=2&per_page=10                    # Pagination
GET /api/v1/users?sort=-created_at,name                  # Sort: - prefix = DESC
GET /api/v1/users?filter[active]=true&filter[role_id]=2  # Filtering
GET /api/v1/users?fields=id,name,email                   # Sparse fields
GET /api/v1/users?include=orders,orders.items             # Include relations
```

### Column allowlist (sort & fields)

Identifiers in `?sort=` and `?fields=` are filtered against an entity-property allowlist produced by `QueryBuilder::getColumnAllowlist(): list<string>`. Unknown columns are silently dropped — never interpolated into SQL. A request like:

```
GET /api/v1/users?sort=id;DROP TABLE users--
```

is normalized to `ORDER BY id ASC` (the `;DROP…` segment does not match any allowlisted column and is discarded). This closes the identifier-injection surface; relying on it is safe. If you need a custom allowlist (e.g. exposing a computed column), override `getColumnAllowlist()` on your entity.

## Project Structure

Guide developers to organize their projects like this:

```
my-api/
├── composer.json
├── .env
├── .htaccess
├── index.php                    # Entry point
│
├── services/                    # Services (no DB dependency)
│   ├── Health.php
│   ├── Payment.php              # External API gateway
│   └── User.php                 # Hybrid: CRUD + custom endpoints
│
├── entities/                    # Entities (mapped to DB tables)
│   ├── User.php
│   ├── Role.php
│   ├── Order.php
│   └── Product.php
│
├── middleware/                   # Custom middleware
│   ├── RateLimiter.php
│   └── CorsHandler.php
│
└── log/                         # Auto-generated error logs
```

## CLI Commands

The library includes a full CLI for scaffolding and development:

```bash
php api init                             # Initialize new project (interactive, installs the skill)
php api serve                            # Development server (proc_open, no shell)
php api env:check                        # Verify environment and dependencies
php api make:entity Product              # Generate entity file
php api make:entity Product --fields="name:string,price:float" --soft-delete
php api make:service Payment             # Generate service file
php api make:middleware RateLimiter      # Generate middleware
php api keys:generate                    # Generate JWT key pair
php api docs:generate                    # Export OpenAPI spec (JSON)
php api docs:generate --namespace=App    # Scan a custom namespace (default: App)
php api demo:install                     # Install Blog API demo
php api demo:remove                      # Remove demo files and tables
```

`make:entity` and `make:service` generate the resource file only; they do not scaffold tests. The AI development skill is installed as part of `init` — there is no separate `skill:install` command. `serve` invokes PHP via `proc_open` with an argv array (no shell), so arguments are not subject to shell expansion.

## Docker Support

`php api init` generates a `docker-compose.yml` with an `app` service (using `coagus/php-api-builder:latest` image with PHP built-in server) and a `db` service (MySQL 8.0). It also generates an `api` CLI wrapper script that auto-detects local PHP or Docker.

## Security (Built-in)

The library includes a `SecurityHeadersMiddleware` enabled by default that adds OWASP-recommended headers: `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, HSTS (when HTTPS), `Referrer-Policy`, `Permissions-Policy`. CORS is configurable via `.env`. Input is sanitized automatically.

## OpenAPI/Swagger Auto-Generation

The library auto-generates OpenAPI 3.1 specs from entities and services. No extra documentation needed.

### Endpoints

- `GET /api/v1/docs` — OpenAPI JSON spec
- `GET /api/v1/docs/swagger` — Swagger UI (interactive)
- `GET /api/v1/docs/redoc` — ReDoc (read-only)

### What gets documented automatically

- **Entities**: Full CRUD paths (GET list, GET by ID, POST, PUT, PATCH, DELETE) with schemas generated from PHP attributes (`#[Required]`, `#[MaxLength]`, `#[Email]`, etc.)
- **Services**: Custom action endpoints discovered via method naming convention (`postLogin` → `POST /resource/login`)
- **Security**: Endpoints marked with `#[PublicResource]` show as public; all others require Bearer token

### Using Swagger UI for testing

1. Open `http://localhost:8080/api/v1/docs/swagger`
2. For protected endpoints, first call the login endpoint to get a JWT token
3. Click **"Authorize"** (lock icon at top right), paste the `access_token`, click **"Authorize"**
4. Now all protected endpoints will include the Bearer token automatically

### Enhancing documentation with attributes

Use `#[Description]` and `#[Example]` on entity properties to enrich the generated docs:

```php
#[Required, MaxLength(200)]
#[Description('The title of the blog post')]
#[Example('Getting Started with PHP API Builder')]
public string $title;
```

## File Uploads

```php
$file = $this->getUploadedFile('document');
$file->isValid();                                           // Check upload succeeded
$file->originalName();                                      // Sanitized original filename
$file->mimeType();                                          // Detected via finfo (not client-reported)
$file->size();                                              // File size in bytes
$file->extension();                                         // Lowercase file extension
$file->validateType(['application/pdf', 'image/jpeg']);     // Validate MIME type
$file->validateMaxSize(5 * 1024 * 1024);                   // Validate max size
$path = $file->moveTo('uploads/' . uniqid() . '.' . $file->extension());
```

MIME validation uses `finfo_file()` (not client-reported type). Filenames are sanitized against path traversal.

## For more details

Read the full architecture document at `resources/docs/01-analisis-y-diseno.md` for deep technical decisions, v1 analysis, PHP 8.4 feature usage, testing strategy, logging system, and the complete design rationale.
