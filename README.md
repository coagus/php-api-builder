[![Latest Stable Version](https://poser.pugx.org/coagus/php-api-builder/v/stable)](https://packagist.org/packages/coagus/php-api-builder)
[![Total Downloads](https://poser.pugx.org/coagus/php-api-builder/downloads)](https://packagist.org/packages/coagus/php-api-builder)
[![License](https://poser.pugx.org/coagus/php-api-builder/license)](https://packagist.org/packages/coagus/php-api-builder)
[![Tests](https://github.com/coagus/php-api-builder/actions/workflows/release.yml/badge.svg)](https://github.com/coagus/php-api-builder/actions)
[![PHP 8.4+](https://img.shields.io/badge/php-8.4%2B-blue.svg)](https://www.php.net/)
[![Docker](https://img.shields.io/badge/docker-ready-2496ED.svg)](https://hub.docker.com/r/coagus/php-api-builder)

# PHP API Builder v2

Build RESTful APIs in PHP in minutes. Define your entities, get CRUD automatically, and focus on your business logic.

```php
#[Table('products')]
#[SoftDelete]
class Product extends Entity
{
    #[PrimaryKey]
    public private(set) int $id;

    #[Required, MaxLength(100)]
    public string $name { set => trim($value); }

    #[Required]
    public float $price {
        set {
            if ($value < 0) throw new \InvalidArgumentException('Price must be positive');
            $this->price = round($value, 2);
        }
    }

    #[Required, Email, Unique]
    public string $email { set => strtolower(trim($value)); }

    #[Hidden]
    public string $passwordHash = '';

    #[Ignore]
    public string $password {
        set => $this->passwordHash = password_hash($value, PASSWORD_ARGON2ID);
    }

    #[BelongsTo(Category::class)]
    public int $categoryId;

    #[HasMany(Review::class)]
    public array $reviews;
}
```

That's it. You now have a fully functional API with `GET`, `POST`, `PUT`, `PATCH`, `DELETE` endpoints, pagination, filtering, sorting, validation, soft deletes, and relationships. No controllers, no routes to configure, no boilerplate.

## Features

- **Automatic CRUD** from entity definitions with zero configuration
- **Powerful ORM** with Active Record pattern, relationships, and 5-level Query Builder
- **PHP 8.4** property hooks, asymmetric visibility, typed properties, attributes as metadata
- **Multi-database** support via PDO (MySQL, PostgreSQL, SQLite)
- **JWT Authentication** with OAuth 2.1 security practices (short-lived tokens, refresh rotation, scopes)
- **Auto-generated OpenAPI/Swagger** documentation from your entity attributes
- **Validation via attributes** (`#[Required]`, `#[Email]`, `#[MaxLength]`, `#[Unique]`) -- no config files
- **Rate limiting** middleware with file-based storage -- no external dependencies
- **REST conventions** -- lowerCamelCase JSON keys, snake_case query params, RFC 7807 errors
- **Security built-in** with OWASP headers, CORS, input sanitization, SQL injection protection
- **Docker-first** workflow -- start a project without PHP installed locally
- **CLI scaffolding** for entities, services, and middleware
- **Error traceability** with request ID correlation across all layers
- **AI development skill** included -- install it and your AI assistant knows the library

## Quick Start

### With PHP installed

1. Create a new project:

```bash
composer create-project coagus/php-api-builder-skeleton my-api
```

2. Initialize and start:

```bash
cd my-api && ./api init
```

### Without PHP (Docker only)

1. Create your project directory:

```bash
mkdir my-api && cd my-api
```

2. Initialize the project:

```bash
docker run --rm -it -v $(pwd):/app coagus/php-api-builder init
```

3. Start the services:

```bash
docker compose up -d
```

4. Verify it works:

```bash
curl http://localhost:8080/api/v1/health
```

> **Running CLI commands without PHP:** Once `docker compose up -d` is running, enter the container and use the CLI from there:
> ```bash
> docker compose exec app bash
> php vendor/bin/api make:entity Product
> ```
> Alternatively, the `./api` wrapper auto-detects Docker and works without entering the container.

## Try the Demo

Explore all library features with a ready-made Blog API demo:

1. Install the demo (after init + docker compose up):

```bash
./api demo:install
```

2. Open Swagger UI at `http://localhost:8080/api/v1/docs/swagger`

3. When done exploring, clean up:

```bash
./api demo:remove
```

The demo creates a complete Blog API with Users, Posts, Comments, and Tags -- showcasing entities, services, relationships, JWT auth, validation, rate limiting, middleware, and OpenAPI documentation.

## Create Your First Entity

```bash
./api make:entity User --fields="name:string,email:string,password:string" --soft-delete
```

This generates `entities/User.php`:

```php
<?php
namespace App\Entities;

use Coagus\PhpApiBuilder\ORM\Entity;
use Coagus\PhpApiBuilder\Attributes\{Table, PrimaryKey, SoftDelete};
use Coagus\PhpApiBuilder\Validation\Attributes\{Required};

#[Table('users')]
#[SoftDelete]
class User extends Entity
{
    #[PrimaryKey]
    public private(set) int $id;

    #[Required]
    public string $name { set => trim($value); }

    #[Required]
    public string $email;

    #[Required]
    public string $password;
}
```

Your endpoints are ready:

```
GET    /api/v1/users              # List with pagination, filtering, sorting
GET    /api/v1/users/{id}         # Get one
POST   /api/v1/users              # Create (validates automatically)
PUT    /api/v1/users/{id}         # Full update
PATCH  /api/v1/users/{id}         # Partial update
DELETE /api/v1/users/{id}         # Soft delete
```

## Services (No Database)

Not everything needs a database. Services handle external APIs, health checks, custom logic:

```php
#[PublicResource]
#[Route('health')]
class Health extends Service
{
    public function get(): void
    {
        $this->success([
            'status' => 'healthy',
            'timestamp' => date('c'),
        ]);
    }
}
```

## Well-known routes (RFC 8615)

Not every URL belongs under `/api/v1`. RFC 8615 reserves the `/.well-known/*` namespace for host-level metadata: OpenID Connect discovery, OAuth 2.0 authorization-server metadata, JWKS, `security.txt`, and similar. Register these paths with the optional third constructor argument:

```php
use Coagus\PhpApiBuilder\API;
use App\Services\Jwk;
use App\Services\OpenIdConfig;

$api = new API(
    namespace: 'App\\Services',
    apiPrefix: '/api/v1',
    wellKnown: [
        '/.well-known/jwks.json'             => [Jwk::class, 'get'],
        '/.well-known/openid-configuration'  => [OpenIdConfig::class, 'get'],
    ]
);

$api->run()->send();
```

The dispatcher consults the `wellKnown` map before the `apiPrefix` router, so these paths resolve regardless of the prefix value. Each handler is a regular `Service` (extends `Coagus\PhpApiBuilder\Resource\Service`) — its `get()` method writes a response with `$this->success(...)` exactly like any other service.

Malformed entries fail fast at construction time. If the class does not exist, the method is not callable on an instance of that class, or the tuple is not `[Class::class, 'method']`, the `API` constructor throws `InvalidArgumentException` before any request is served.

A few notes:
- Global middleware registered via `API::middleware([...])` (CORS, security headers, rate limit) still runs for well-known routes. Per-route `#[Middleware]` attributes are not applied — these endpoints are handled outside the router's class-discovery path.
- Well-known paths are deliberately **not** emitted in the auto-generated OpenAPI document, which only describes `$apiPrefix`-scoped resources.
- The `wellKnown` map is optional. Omitting it preserves the exact behavior of previous releases.

## Hybrid Resources (CRUD + Custom Endpoints)

Combine automatic CRUD with custom business logic:

```php
class UserService extends APIDB
{
    protected string $entity = User::class;

    // CRUD works automatically: GET, POST, PUT, PATCH, DELETE

    // Custom: POST /api/v1/users/login
    public function postLogin(): void
    {
        $input = $this->getInput();
        $user = User::query()->where('email', $input->email)->first();

        if (!$user || !password_verify($input->password, $user->password)) {
            $this->error('Invalid credentials', 401);
            return;
        }

        $this->success(['token' => Auth::generateAccessToken($user->toArray())]);
    }
}
```

## Per-route Middleware

Attach middleware to a specific resource class or HTTP method with the `#[Middleware]` attribute. Parameters are forwarded to the middleware constructor as named arguments, and the attribute is repeatable:

```php
use Coagus\PhpApiBuilder\Attributes\Middleware;
use Coagus\PhpApiBuilder\Http\Middleware\RateLimitMiddleware;
use Coagus\PhpApiBuilder\Http\Middleware\AuthMiddleware;

class Reports extends APIDB
{
    protected string $entity = Report::class;

    // Tight per-endpoint budget, independent of the global stack.
    #[Middleware(RateLimitMiddleware::class, limit: 10, windowSeconds: 60)]
    public function get(): void
    {
        // ...
    }

    // Multiple middlewares stack in declaration order.
    #[Middleware(AuthMiddleware::class)]
    #[Middleware(RateLimitMiddleware::class, limit: 3, windowSeconds: 60)]
    public function postExport(): void
    {
        // ...
    }
}
```

The dispatch pipeline runs global middleware first (registered via `API::middleware([...])`), then class-level `#[Middleware]`, then method-level `#[Middleware]`, then the handler. The middleware class must implement `MiddlewareInterface`; otherwise dispatch fails loudly.

## Virtual Property Hooks with `#[Ignore]`

`#[Ignore]` marks a public property as invisible to the ORM, validator, and OpenAPI generator. It pairs naturally with a PHP 8.4 `set`-only hook that writes to a sibling backing column:

```php
#[Hidden]
public string $passwordHash = '';

#[Ignore]
public string $password {
    set => $this->passwordHash = password_hash($value, PASSWORD_ARGON2ID);
}
```

An `#[Ignore]` property is not written to INSERT/UPDATE, not hydrated from SELECT rows, not checked by the validator, not emitted in response bodies, and not surfaced in OpenAPI schemas. Use it whenever the property exists only to transform input — never as a persisted column.

## Query Builder

Five levels of complexity -- use what you need:

```php
// Level 1: Shortcuts
$user = User::find(1);
$users = User::all();

// Level 2: Fluent
$users = User::query()
    ->where('active', true)
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();

// Level 3: Eager loading (no N+1 queries)
$users = User::query()
    ->with('orders', 'orders.items')
    ->where('active', true)
    ->get();

// Level 4: Reusable scopes
$users = User::query()->active()->recent(7)->get();

// Level 5: Raw SQL (always parameterized)
$results = Connection::getInstance()->query(
    'SELECT u.*, COUNT(o.id) as total FROM users u LEFT JOIN orders o ON o.user_id = u.id GROUP BY u.id HAVING total > ?',
    [5]
);
```

## Validation via Attributes

```php
#[Table('users')]
class User extends Entity
{
    #[Required, MaxLength(50)]
    public string $name { set => trim($value); }

    #[Required, Email, Unique]
    public string $email { set => strtolower(trim($value)); }

    #[Hidden]
    public string $passwordHash = '';

    #[Ignore]
    public string $password {
        set => $this->passwordHash = password_hash($value, PASSWORD_ARGON2ID);
    }
}
```

`#[Required]` validates presence, `#[Email]` validates format, `#[Unique]` checks the database, `#[Hidden]` excludes the field from responses, `#[Ignore]` hides a virtual property from ORM/validator/schemas, and property hooks sanitize on assignment.

## Auto-Generated API Documentation

Your entity attributes generate OpenAPI 3.1 specs automatically:

```
GET /api/v1/docs            # OpenAPI JSON spec
GET /api/v1/docs/swagger    # Swagger UI
GET /api/v1/docs/redoc      # ReDoc UI
```

No extra annotations needed. `#[Required]` becomes `required`, `#[MaxLength(50)]` becomes `maxLength: 50`, `#[Hidden]` fields are excluded from response schemas.

## CLI Commands

```bash
./api init                   # Initialize new project (interactive)
./api serve                  # Development server
./api env:check              # Verify environment and dependencies
./api make:entity Product    # Generate entity class
./api make:service Payment   # Generate service class
./api make:middleware Auth    # Generate middleware
./api keys:generate          # Generate JWT key pair
./api docs:generate          # Export OpenAPI spec
./api demo:install           # Install Blog API demo
./api demo:remove            # Remove demo files and tables
```

The `./api` wrapper detects whether to use local PHP or Docker automatically. Teams with mixed setups work seamlessly.

## Database Support

Configure in `.env`:

```bash
DB_DRIVER=mysql    # mysql | pgsql | sqlite
DB_HOST=localhost
DB_PORT=3306
DB_NAME=my_database
DB_USER=root
DB_PASSWORD=secret
```

The ORM generates driver-specific SQL through PDO. Switch databases by changing one line.

## Security

Built-in by default, following OWASP recommendations:

- **SQL Injection**: Parameterized queries everywhere (PDO prepared statements)
- **Security Headers**: `X-Content-Type-Options`, `X-Frame-Options`, HSTS, `Referrer-Policy`
- **JWT Auth**: Short-lived access tokens (15min), refresh rotation with theft detection
- **Input Sanitization**: Automatic null byte removal, configurable per-field
- **CORS**: Configurable via `.env`, validates against dangerous misconfigurations
- **Sensitive Data**: `#[Hidden]` attributes, `SensitiveDataFilter` in logs

## Project Structure

```
my-api/
├── api                     # CLI wrapper (auto-detects PHP vs Docker)
├── .env                    # Configuration
├── index.php               # Entry point
├── entities/               # Database entities (auto CRUD)
│   ├── User.php
│   └── Product.php
├── services/               # Pure services (no DB)
│   ├── Health.php
│   └── AuthMiddleware.php   # Custom middleware (also in services/)
├── tests/                  # Pest tests
├── docker-compose.yml      # Docker environment
└── log/                    # Error logs (auto-generated)
```

## Requirements

- PHP 8.4+
- Composer 2.x
- Or just Docker

## Installation

```bash
composer require coagus/php-api-builder
```

## Documentation

Full architecture and design documentation is available in [resources/docs/01-analisis-y-diseno.md](resources/docs/01-analisis-y-diseno.md). Canonical Mermaid diagrams (C4 Container, request lifecycle, auth sequence, entity model, rate limit) live under [resources/docs/diagrams/](resources/docs/diagrams/).

## AI Development Skill

The library includes an AI skill that teaches Claude Code and Cowork how to work with php-api-builder. It is installed automatically by `./api init` into `.claude/skills/php-api-builder/`, so your AI assistant can generate entities, services, queries, and configurations following the library's patterns.

## License

MIT License. See [LICENSE](LICENSE) for details.

## Author

Christian Agustin - [christian@agustin.gt](mailto:christian@agustin.gt)
