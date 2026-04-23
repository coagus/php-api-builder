# Request Lifecycle

```mermaid
flowchart TD
    Start([HTTP request]) --> Entry[API::run]
    Entry --> Pipeline[[Middleware pipeline]]
    Pipeline --> CORS[CorsMiddleware]
    CORS --> Security[SecurityHeadersMiddleware]
    Security --> Auth{AuthMiddleware<br/>JWT or API key}
    Auth -- missing or invalid --> Err401[401 Unauthorized<br/>RFC 7807]
    Auth -- public path or valid --> Rate{RateLimitMiddleware<br/>count <= limit?}
    Rate -- over limit --> Err429[429 Too Many Requests<br/>Retry-After]
    Rate -- within limit --> Dispatch[API::dispatch]
    Dispatch --> WellKnown{wellKnown hit?<br/>RFC 8615 /.well-known/*}
    WellKnown -- yes --> WellKnownHandler[Invoke registered handler<br/>class + method tuple]
    WellKnown -- no --> Docs{Docs route?<br/>/api/v1/docs*}
    Docs -- yes --> DocsCtrl[DocsController<br/>Swagger / ReDoc / JSON spec]
    Docs -- no --> Route{Router::resolve}
    Route -- no match --> Err404[404 Not Found]
    Route -- method mismatch --> Err405[405 Method Not Allowed]
    Route -- matched --> Kind{Resource kind?}
    Kind -- Service / APIDB subclass --> Handler[Instantiate subclass]
    Kind -- plain Entity --> Wrap[Wrap in generic APIDB]
    Handler --> RouteMW[[Per-route middleware pipeline<br/>#Middleware class-level then method-level]]
    Wrap --> RouteMW
    RouteMW --> Invoke[Invoke verb handler<br/>get / post / put / patch / delete<br/>or custom postAction]
    Invoke --> Resp([Response + X-Request-ID + rate-limit headers])
    WellKnownHandler --> Resp
    DocsCtrl --> Resp
    Err401 --> Resp
    Err404 --> Resp
    Err405 --> Resp
    Err429 --> Resp
```

**Figure 2 — Request lifecycle.** Every request passes through the middleware pipeline registered on `API::middleware()` before the router matches a resource. The default order is CORS → security headers → auth → rate limit, but the order is whatever the application registers. Inside `API::dispatch()` the well-known lookup runs first: if the request path matches an entry registered via the `wellKnown` constructor argument (RFC 8615 discovery endpoints — JWKS, OpenID Connect configuration, security.txt, etc.), the registered `[class, method]` handler is invoked directly, bypassing the `apiPrefix` router. Otherwise the docs route is checked, and finally `Router::resolve()` maps kebab-case URL segments to PascalCase class names in the application namespace; a bare `Entity` subclass is wrapped at runtime in a generic `APIDB` to expose standard CRUD verbs. After the handler is resolved, `MiddlewareResolver` reads `#[Middleware]` attributes from the resource class and the matched method and runs them (in that order) before the verb handler executes; see `diagrams/middleware-pipeline.md` for the layered detail. Error branches return RFC 7807 problem responses. See `src/API.php::run()`, `src/API.php::dispatch()`, `src/API.php::handleWellKnown()`, `src/Http/Middleware/MiddlewareResolver.php`, and `src/Http/Middleware/MiddlewarePipeline.php`.
