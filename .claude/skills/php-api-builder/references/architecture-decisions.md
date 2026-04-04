# Architecture Decisions Reference

This document summarizes the key technical decisions for php-api-builder v2 and the reasoning behind each one. When the skill needs deeper context about "why" something is designed a certain way, consult this file.

## Table of Contents

1. Active Record vs Data Mapper
2. PHP 8.4 Pragmatic OOP
3. JWT with OAuth 2.1 Practices (not full OAuth server)
4. PDO Multi-Driver
5. Hooks vs Events
6. Inherited Response Methods vs Return Values
7. Pest vs PHPUnit
8. Error-Only Logging with Request ID Correlation

---

## 1. Active Record vs Data Mapper

**Decision:** Active Record

**Why:** The library's philosophy is "easy to use, fast, easy to interpret." Active Record puts persistence logic directly in the entity (`$user->save()`), which means:
- Less boilerplate (no separate Repository classes)
- Intuitive for developers coming from Eloquent/Rails
- Entities are self-contained: define schema + validation + hooks + persistence in one class
- Aligned with the library's goal of zero-config CRUD

Data Mapper (used by Doctrine) would require separate Entity + Repository + Unit of Work, tripling the number of files. For a lightweight Composer package, that's overhead without proportional benefit.

**Trade-off accepted:** Entities have more responsibility (violates SRP slightly), but PHP 8.4 property hooks and attributes keep them clean and readable.

## 2. PHP 8.4 Pragmatic OOP

**Decision:** Use PHP 8.4's new features to get "simple AND correct" — best of both worlds.

**The problem in v1:** Everything was `public` with no type safety. Clean code would require private properties + getters/setters, making entities verbose and harder to use.

**PHP 8.4 solution:**
- `public private(set)` — readable by anyone, writable only internally (e.g., `$id`)
- Property hooks (`set =>`) — validation/sanitization runs automatically on assignment without explicit setters
- Typed properties — type safety without ceremony
- Constructor promotion — less boilerplate in config/value objects

This means `$user->name = " Carlos "` automatically trims, and `$user->id` can't be modified externally — all without writing a single getter or setter method.

## 3. JWT with OAuth 2.1 Practices

**Decision:** JWT tokens using `firebase/php-jwt` with OAuth 2.1 security practices, NOT a full OAuth 2.0 server.

**Why not league/oauth2-server:** Adding a full OAuth server would require 7+ additional dependencies, database migrations for auth codes/clients/grants, and complex configuration. The library is a lightweight API builder, not an auth framework.

**What we take from OAuth 2.1:**
- Short-lived access tokens (15 min)
- Refresh token rotation (new refresh token on each use)
- Refresh token reuse detection (possible token theft → revoke all)
- Scopes for fine-grained authorization
- RS256/ES256 recommended over HS256

**What we skip:** Authorization code flow, client credentials, device flow, PKCE (these are for OAuth servers, not API libraries).

## 4. PDO Multi-Driver

**Decision:** PDO abstraction with driver interface for MySQL, PostgreSQL, SQLite.

**Why PDO over MySQLi:** MySQLi only supports MySQL. PDO supports 12+ databases through a unified API. Since the library generates SQL through a QueryBuilder, the driver just handles dialect differences (LIMIT syntax, identifier quoting, date functions).

**Driver architecture:**
```
Connection (singleton) → DriverInterface → MySqlDriver / PgSqlDriver / SqliteDriver
```

Each driver implements dialect-specific SQL generation. The QueryBuilder delegates to the active driver for any syntax that varies.

## 5. Hooks vs Events

**Decision:** Entity lifecycle hooks (methods on the entity class), not a global event system.

**Why hooks:**
- Simpler mental model: the logic lives where the data lives
- No event dispatcher to configure, no listeners to register
- Consistent with Active Record philosophy
- `beforeCreate()`, `afterCreate()`, etc. are just protected methods — override what you need

**Why not events:** A full event system (EventDispatcher, Listeners, Subscribers) adds complexity appropriate for large frameworks (Symfony, Laravel), not a lightweight library. If a developer needs cross-cutting concerns, middleware handles that at the HTTP level.

## 6. Inherited Response Methods

**Decision:** Resource base class provides `$this->success()`, `$this->error()`, etc.

**Why not return values:** Returning arrays/objects from controller methods requires a framework layer to interpret and convert them. Inherited methods give direct control over HTTP response (status code, headers, body) with a clean API. This also matches how v1 worked (global `success()` function) but moves it to proper OOP.

**Auto-wrap in APIDB:** Standard CRUD operations automatically format responses (GET list → paginated JSON, POST → 201 Created, DELETE → 204 No Content). Custom methods in services use explicit `$this->success()`.

## 7. Pest as Primary Test Framework

**Decision:** Pest v4 (built on PHPUnit 12) for 80-90% of tests.

**Why Pest over raw PHPUnit:** Expressive syntax, less boilerplate, built-in `expect()` API, type coverage checking. Since it's built ON PHPUnit, there's zero lock-in — any PHPUnit test works inside Pest.

**Test DB:** SQLite in-memory (`:memory:`) for speed and isolation. Each test gets a fresh database.

## 8. Error-Only Logging with Request ID

**Decision:** Log only errors, but with complete context including a unique request ID.

**Why not log all requests:** That's the web server's job (Apache/Nginx access logs). The library focuses on actionable information: what went wrong and how to reproduce it.

**Request ID flow:** Generated at entry point (`bin2hex(random_bytes(8))`), propagated through `RequestContext`, included in every log entry AND in error responses to the client. Support can correlate a user's error report directly to the full error context in logs.

**Sensitive data:** Automatically filtered via `SensitiveDataFilter` — passwords, tokens, API keys are replaced with `***PROTECTED***` in logs.
