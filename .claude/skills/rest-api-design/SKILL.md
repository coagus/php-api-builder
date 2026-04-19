---
name: rest-api-design
description: Expert on REST API design, HTTP semantics, RFC 7807 error format, pagination patterns, versioning, idempotency, and OpenAPI 3.1. Use when designing new endpoints, choosing HTTP verbs and status codes, shaping request/response payloads, or reviewing REST compliance for the php-api-builder library.
---

# REST API Design — HTTP Done Right

This skill covers the user-facing contract: URLs, verbs, statuses, payloads, errors. It's what the library exposes and what auditors will check against.

## Resource modeling

### URLs are nouns, not verbs

```
GET    /api/v1/users               # list
GET    /api/v1/users/{id}          # one
POST   /api/v1/users               # create
PUT    /api/v1/users/{id}          # full replace
PATCH  /api/v1/users/{id}          # partial update
DELETE /api/v1/users/{id}          # delete

# BAD:
POST /api/v1/getUser
POST /api/v1/deleteUser?id=42
```

For an action that doesn't map to CRUD, model it as a sub-resource or a state transition:

```
POST /api/v1/orders/{id}/cancel       # state transition (not GET/DELETE)
POST /api/v1/users/{id}/password-reset # invokes a process
POST /api/v1/users/login              # auth is its own thing — acceptable
```

### Nesting

One level deep is fine. Two is the ceiling. Three is a code smell.

```
GET /api/v1/users/{id}/orders         # OK
GET /api/v1/users/{id}/orders/{oid}   # OK, but only if orders don't exist without the user context
```

Once a resource has its own identity, promote it to a top-level URL and use query params to filter:

```
GET /api/v1/orders?user_id=42
```

### Plural, kebab-case

- Plural: `/users`, `/orders`, `/user-profiles`.
- Kebab-case for multi-word: `/user-profiles`, not `/userProfiles` or `/user_profiles`.

## HTTP methods and semantics

| Method | Safe | Idempotent | Purpose |
|---|---|---|---|
| GET | Yes | Yes | Read only, no side effects |
| HEAD | Yes | Yes | Metadata only (no body) |
| OPTIONS | Yes | Yes | Discovery, CORS preflight |
| POST | No | No | Create, or non-CRUD action |
| PUT | No | Yes | Full replacement of a resource |
| PATCH | No | No (usually) | Partial update |
| DELETE | No | Yes | Remove resource |

**Safe**: no state change on server.
**Idempotent**: repeating the same request yields the same server state.

Common mistakes:
- `GET` with side effects (don't — browsers prefetch GETs).
- `PUT` that partially updates (that's `PATCH`).
- `POST /resources/{id}` to update (that's `PUT` or `PATCH`).

## Status codes — use the right one

### 2xx Success
- `200 OK` — generic success with body.
- `201 Created` — resource created. Include `Location` header pointing to it.
- `202 Accepted` — async processing started.
- `204 No Content` — success, no body (DELETE, sometimes PUT/PATCH).

### 3xx Redirection
- `301 Moved Permanently` — permanent URL change.
- `304 Not Modified` — for conditional GETs (`If-None-Match`).

### 4xx Client errors
- `400 Bad Request` — malformed syntax.
- `401 Unauthorized` — auth missing/invalid (despite the name, it's "unauthenticated").
- `403 Forbidden` — authenticated but not allowed.
- `404 Not Found` — resource doesn't exist.
- `405 Method Not Allowed` — wrong verb. Include `Allow` header with valid verbs.
- `409 Conflict` — state conflict (e.g., unique constraint violation on create).
- `410 Gone` — was here, deleted, won't come back.
- `415 Unsupported Media Type` — request `Content-Type` not accepted.
- `422 Unprocessable Entity` — syntax OK, semantics/validation failed.
- `429 Too Many Requests` — rate limit exceeded. Include `Retry-After`.

### 5xx Server errors
- `500 Internal Server Error` — unexpected failure. Never leak stack traces.
- `502 Bad Gateway` — upstream dependency failed.
- `503 Service Unavailable` — down for maintenance. Include `Retry-After`.

Don't use 418. Don't use 200 with `{"error": ...}` in the body. Use real HTTP statuses.

## Request/response shape

### JSON conventions

- `Content-Type: application/json; charset=utf-8` on both request and response.
- JSON keys: `lowerCamelCase` — `firstName`, `createdAt`, `totalPages`.
- URL query params: `snake_case` — `?per_page=20&sort_by=name`.
- Booleans: `true`/`false`, not `"true"`/`"1"`/`0`.
- Timestamps: ISO 8601 in UTC — `"2026-04-18T12:34:56Z"`. Avoid Unix timestamps in JSON.
- Money: string if precision matters — `"12.50"` — or integer cents — `1250`.
- Null for absent values; never omit keys when null is meaningful.

### Envelopes

Use a consistent envelope for collections vs single resources:

```json
// Single
{ "data": { "id": 1, "name": "Ada" } }

// Collection (paginated)
{
    "data": [ { "id": 1 }, { "id": 2 } ],
    "meta": {
        "currentPage": 1,
        "perPage": 20,
        "total": 150,
        "totalPages": 8
    }
}
```

Don't mix: single-resource responses shouldn't use `meta`; collections shouldn't return a bare array at the top level (hard to evolve).

## Errors — RFC 7807 (problem+json)

Use RFC 7807 for every error response. It's the standard the library already emits.

```json
{
    "type": "https://api.example.com/errors/validation",
    "title": "Validation Error",
    "status": 422,
    "detail": "The field 'email' is not a valid email address",
    "instance": "/api/v1/users",
    "requestId": "a3f4b2c1e9d80716",
    "errors": [
        { "field": "email", "code": "invalid_format", "message": "..." },
        { "field": "age",   "code": "out_of_range",  "message": "..." }
    ]
}
```

Required fields per RFC 7807: `type`, `title`, `status`, `detail`.
Useful extensions: `instance`, `requestId`, `errors` (for validation batching).

`Content-Type: application/problem+json` per the RFC.

Anti-patterns:
- `{"error": "..."}` — not RFC 7807.
- `{"success": false, "data": null}` — bolted-on success flag.
- Leaking stack traces or SQL errors in `detail`.

## Pagination

Three patterns — pick one per API and stick with it.

### Offset pagination (simplest, breaks on deep pages)
```
GET /users?page=2&per_page=20
```
Good for: admin UIs with < 10k records.

### Keyset/cursor pagination (scalable)
```
GET /users?after=eyJpZCI6NDJ9&per_page=20
```
Response includes `nextCursor` in `meta`. Good for: feeds, infinite scroll.

### Link headers (HATEOAS)
```
Link: <.../users?page=3>; rel="next", <.../users?page=8>; rel="last"
```
RESTful but harder to consume — most clients ignore.

The library uses offset (`page`/`per_page`). Follow that unless a specific endpoint needs cursor semantics.

## Filtering, sorting, sparse fields

```
GET /users?filter[active]=true&filter[role_id]=2    # filter
GET /users?sort=-created_at,name                      # sort; -prefix = desc
GET /users?fields=id,name,email                       # sparse fields
GET /users?include=orders,orders.items                # eager load relationships
```

These are the library's conventions. When designing a new endpoint, support them consistently — don't invent new param names.

## Idempotency

POSTs are not idempotent by default. For payment/create flows where the client might retry:

```
POST /api/v1/payments
Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000
```

Server stores `(key → response)` for 24h. Duplicate key returns the original response without re-processing.

For state transitions that are naturally idempotent (`POST /orders/{id}/cancel` on an already-cancelled order), return 200/204 + message, not an error.

## Versioning

URL-based (`/api/v1`, `/api/v2`) is the simplest. Header-based (`Accept: application/vnd.api+json;v=2`) is cleaner but harder to debug.

The library uses URL versioning. Follow it.

### When to bump the version
- **Breaking change**: removed field, renamed field, changed type, stricter validation.
- **Not breaking**: new optional field, new endpoint, new query param.

Add, deprecate, remove — in that order, with overlap periods.

## Caching (`Cache-Control`, `ETag`)

For GETs on stable resources:

```
ETag: "b7f8c9..."
Cache-Control: private, max-age=60
```

Client sends `If-None-Match: "b7f8c9..."`; you return `304 Not Modified` if unchanged.

For list endpoints or authenticated data: `Cache-Control: no-store` to keep things simple.

## Rate limiting

Emit the standard headers:
```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 97
X-RateLimit-Reset: 1680000060
```

On 429, include `Retry-After: 30` (seconds) or an HTTP-date.

## Authentication

- JWT Bearer: `Authorization: Bearer <token>`.
- API Key: custom header like `X-API-Key: ...`. Never accept in query string (logs leak them).
- Rotate, expire, and revoke. JWT access tokens should be short-lived (15min is a good default); use refresh tokens with rotation.

Don't invent new schemes. Use what the library provides.

## CORS

- Never `Access-Control-Allow-Origin: *` with `Allow-Credentials: true` — browsers reject it.
- Allowlist specific origins in `.env`.
- Respond to preflight `OPTIONS` requests with the correct `Access-Control-Allow-Methods` and `Access-Control-Allow-Headers`.

## OpenAPI 3.1

The library auto-generates OpenAPI from attributes. When designing, think about what ends up in the spec:
- `#[Required]` → `required: true`
- `#[MaxLength(n)]` → `maxLength: n`
- `#[Hidden]` → excluded from response schema and create schema
- `#[IsReadOnly]` → in response schema, not in create/update schemas
- `#[Description('...')]` / `#[Example('...')]` → enrich the spec

Before adding a new endpoint, ensure it will render sensibly in Swagger UI. Test by running `./api docs:generate` and opening `/api/v1/docs/swagger`.

## Checklist for a new endpoint

- [ ] URL is a plural noun, kebab-case, correctly scoped.
- [ ] Method matches semantics (GET/POST/PUT/PATCH/DELETE).
- [ ] Status codes are appropriate (201 on create, 204 on delete, 422 on validation).
- [ ] Request/response JSON uses `lowerCamelCase`.
- [ ] Errors use RFC 7807 with `type`, `title`, `status`, `detail`, `requestId`.
- [ ] Pagination/filtering/sorting follow library conventions.
- [ ] Auth requirement is explicit (`#[PublicResource]` or default-protected).
- [ ] Rate-limit headers present.
- [ ] Content-Type is `application/json` (or `application/problem+json` for errors).
- [ ] OpenAPI spec renders correctly in Swagger UI.
