# Well-known Routes Reference (RFC 8615)

When an application needs to expose host-level discovery endpoints — OpenID Connect configuration, OAuth 2.0 authorization-server metadata, JWKS, `security.txt` — those paths live outside `$apiPrefix` by design of the relevant standards. The `API` constructor's third argument registers them.

## API

```php
public function __construct(
    string $namespace,
    string $apiPrefix = '/api/v1',
    array $wellKnown = [],
)
```

`$wellKnown` is a map of `string path` → `[class-string, method-name]` tuple.

## Canonical pattern

```php
use Coagus\PhpApiBuilder\API;
use Coagus\PhpApiBuilder\Attributes\PublicResource;
use Coagus\PhpApiBuilder\Resource\Service;
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

## Handler shape

The handler is a regular `Service` (or any `Resource` subclass). Mark it `#[PublicResource]` if the global auth middleware would otherwise challenge the request.

```php
#[PublicResource]
final class Jwk extends Service
{
    public function get(): void
    {
        $this->success(['keys' => JwkRegistry::all()]);
    }
}
```

For a JWKS, the `success()` body becomes `{ "data": { "keys": [...] } }`. If the consumer expects the top-level RFC 7517 shape (plain `{ "keys": [...] }` without the `data` wrapper), emit the response directly:

```php
use Coagus\PhpApiBuilder\Helpers\ApiResponse;

public function get(): void
{
    $this->response = (new \Coagus\PhpApiBuilder\Http\Response(
        ['keys' => JwkRegistry::all()],
        200,
    ))->header('Content-Type', 'application/jwk-set+json');
}
```

The well-known bypass does not force any specific envelope; the handler fully owns the response body and headers.

## Fail-fast validation

At construction time the `API` constructor validates the `wellKnown` map. Any of these conditions throws `InvalidArgumentException` before the first request is served:

- A key that is not a string or does not start with `/`.
- A value that is not a 2-element list.
- A class in the tuple that does not exist (autoload-checked).
- A method in the tuple that is not callable on an instance of that class.

This mirrors the library's "configuration errors fail loudly, at startup, with a specific message" philosophy (see the CORS `*` + credentials guard, the middleware interface check, etc.).

## Dispatch semantics

1. `API::run()` launches the global middleware pipeline (CORS, security headers, auth, rate limit).
2. Inside `API::dispatch()`, **first** the `wellKnown` map is consulted. If the request path matches, the registered handler is invoked and its response is returned.
3. If there is no match, the docs route and the regular `Router::resolve()` run, in that order.
4. An unregistered `/.well-known/*` path falls through to the router, which returns a 404 `application/problem+json` per RFC 7807.

Implications:

- Well-known paths shadow the router. Registering `/api/v1/foo` in `wellKnown` intercepts the request before the router even sees it.
- Global middleware runs for well-known routes (including rate limit, CORS, security headers).
- Per-route `#[Middleware]` attributes are **not** applied to well-known handlers (the router's `MiddlewareResolver` is skipped).
- Trailing slashes are normalized: `/.well-known/jwks.json` and `/.well-known/jwks.json/` resolve to the same entry.

## Backward compatibility

Omitting `wellKnown` preserves the exact behavior of prior releases. The default is an empty array; the lookup is short-circuited when the map is empty.

## Common discovery endpoints

| Path | Standard | Typical handler |
|------|----------|-----------------|
| `/.well-known/jwks.json` | RFC 7517 (JWK Set) | `Jwk::get` |
| `/.well-known/openid-configuration` | OpenID Connect Discovery 1.0 | `OpenIdConfig::get` |
| `/.well-known/oauth-authorization-server` | RFC 8414 | `OAuthMetadata::get` |
| `/.well-known/security.txt` | RFC 9116 | `SecurityTxt::get` |

## OpenAPI

Well-known paths are **not** emitted in the auto-generated OpenAPI document — that spec describes only the resources discoverable under `$apiPrefix`. Publish discovery semantics via the handler payload itself (standards-compliant JSON served at the well-known URL).

## See also

- `SKILL.md` — Well-known routes (RFC 8615) — compact pattern.
- `src/API.php::handleWellKnown()` — dispatcher integration.
- [RFC 8615](https://www.rfc-editor.org/rfc/rfc8615) — Well-Known Uniform Resource Identifiers.
