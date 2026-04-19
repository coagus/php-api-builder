# Architecture — Container View

```mermaid
C4Container
    title Container view for an API built with php-api-builder

    Person(client, "API Client", "Browser, mobile app, or server-to-server caller")

    System_Boundary(api_sys, "API Application") {
        Container(api, "API (PHP 8.4)", "php-api-builder", "Router, middleware pipeline, Resource/APIDB/Service handlers")
        Container(docs, "OpenAPI Docs", "Swagger UI + ReDoc", "Served from /api/v1/docs, /api/v1/docs/swagger, /api/v1/docs/redoc")
        ContainerDb(ratelimit, "Rate Limit Store", "Filesystem (sys_get_temp_dir)", "Per-IP counter with reset_at window")
        Container(logs, "Log files", "Monolog + PSR-3", "Error logs with request ID correlation")
    }

    ContainerDb(db, "Application Database", "MySQL / PostgreSQL / SQLite via PDO", "Entities, relationships, soft deletes")

    System_Ext(jwt_lib, "firebase/php-jwt", "JWT library", "Signs and verifies access and refresh tokens")

    Rel(client, api, "HTTPS, JSON", "Bearer JWT or API Key")
    Rel(client, docs, "HTTPS", "Swagger UI / ReDoc / raw JSON spec")
    Rel(api, db, "SQL over PDO", "Prepared statements")
    Rel(api, ratelimit, "reads, writes", "flock-guarded files")
    Rel(api, logs, "writes", "PSR-3 records")
    Rel(api, jwt_lib, "uses", "HS256 or RS256")
```

**Figure 1 — Container view.** An API built on `php-api-builder` is a single PHP process that routes requests through a middleware pipeline to Entity-backed or custom handlers. Auth is handled in-process via `firebase/php-jwt`; rate-limit counters persist to disk under the system temp directory; Monolog writes logs with a per-request `X-Request-ID`. The OpenAPI docs endpoints (Swagger UI, ReDoc, raw spec) are served by `DocsController` and discovered at runtime from `entities/` and `services/`. See `src/API.php`, `src/Router.php`, and `src/Http/Middleware/`.
