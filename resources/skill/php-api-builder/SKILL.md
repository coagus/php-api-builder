---
name: php-api-builder
description: "Development assistant for building RESTful APIs with the coagus/php-api-builder v2 library. Use this skill whenever the user mentions php-api-builder, wants to create PHP API entities, services, middleware, authentication with JWT, query building, or any task related to building a REST API using this Composer package. Also trigger when the user asks about creating CRUD endpoints, defining database entities with PHP attributes, configuring API routing, or setting up ORM relationships (BelongsTo, HasMany, BelongsToMany). This skill knows every pattern, attribute, and convention of the library."
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
| `#[Hidden]` | Property | Excluded from JSON output (e.g., passwords) |
| `#[SoftDelete]` | Class | Enables soft delete (deleted_at column) |
| `#[BelongsTo(Class)]` | Property | Many-to-one relationship |
| `#[HasMany(Class)]` | Property | One-to-many relationship |
| `#[BelongsToMany(Class)]` | Property | Many-to-many (pivot table) |
| `#[PublicResource]` | Class | No auth required for this resource |
| `#[Route('path')]` | Class | Custom URL path override |
| `#[Middleware(Class)]` | Class/Method | Attach middleware |

## Creating an Entity

When the user wants to create a new entity, generate it following this pattern:

```php
<?php

namespace App\Entities;

use Coagus\PhpApiBuilder\ORM\Entity;
use Coagus\PhpApiBuilder\Attributes\{Table, PrimaryKey, SoftDelete};
use Coagus\PhpApiBuilder\Validation\Attributes\{Required, Email, MaxLength, MinLength, Unique, Hidden};
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
    public int $categoryId;

    #[HasMany(Review::class)]
    public array $reviews;                      // loaded via eager loading
}
```

Key points to explain to the developer:
- `public private(set)` on `$id` means anyone can read it but only the entity itself (via DB) can set it
- Property hooks (`set =>`) run automatically on assignment - use for sanitization and validation
- `#[SoftDelete]` adds a `deleted_at` column; `delete()` sets it instead of removing the row
- Relationship properties (`$reviews`) are not DB columns; they're populated via eager loading with `->with('reviews')`
- Default values (like `$active = true`) are used when the field is not provided

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

## JSON Response Formats

### Success (single)
```json
{
    "data": { "id": 1, "name": "Carlos", "email": "carlos@test.com" }
}
```

### Success (list with pagination)
```json
{
    "data": [...],
    "meta": {
        "current_page": 1,
        "per_page": 20,
        "total": 150,
        "total_pages": 8
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
```

Per-resource rate limiting using the `#[Middleware]` attribute:
```php
#[Middleware(new RateLimitMiddleware(limit: 20, windowSeconds: 60))]
class PostService extends APIDB { ... }
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

## Middleware

Create custom middleware following PSR-15:

```php
<?php

namespace App\Middleware;

use Coagus\PhpApiBuilder\Http\Middleware\MiddlewareInterface;
use Coagus\PhpApiBuilder\Http\Request;
use Coagus\PhpApiBuilder\Http\Response;

class RateLimiter implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        // Check rate limit
        if ($this->isRateLimited($request)) {
            return Response::error('Too many requests', 429);
        }

        return $next($request);
    }
}

// Apply to a resource:
#[Middleware(RateLimiter::class)]
class User extends Entity { ... }
```

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

## URL Filtering, Sorting, and Pagination

APIDB endpoints automatically support:

```
GET /api/v1/users?page=2&per_page=10                    # Pagination
GET /api/v1/users?sort=-created_at,name                  # Sort: - prefix = DESC
GET /api/v1/users?filter[active]=true&filter[role_id]=2  # Filtering
GET /api/v1/users?fields=id,name,email                   # Sparse fields
GET /api/v1/users?include=orders,orders.items             # Include relations
```

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
php api init                             # Initialize new project (interactive)
php api serve                            # Development server (PHP built-in)
php api env:check                        # Verify environment and dependencies
php api make:entity Product              # Generate entity + test
php api make:entity Product --fields="name:string,price:float" --soft-delete
php api make:service Payment             # Generate service + test
php api make:middleware RateLimiter       # Generate middleware
php api keys:generate                    # Generate JWT key pair
php api docs:generate                    # Export OpenAPI spec
php api demo:install                     # Install Blog API demo
php api demo:remove                      # Remove demo files and tables
```

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
