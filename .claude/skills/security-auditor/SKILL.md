---
name: security-auditor
description: Application security expertise for PHP REST APIs — OWASP Top 10, JWT, OAuth 2.1, CORS, secrets management, SQL injection, XSS, CSRF, input sanitization, file upload safety, security headers, and dependency auditing. Use when auditing code, reviewing authentication/authorization, handling user input, or checking compliance with OWASP ASVS for the php-api-builder library.
---

# Security Auditor — OWASP & Application Security

This skill is for finding and preventing security bugs. Think adversarially: "how would I break this?"

## OWASP Top 10 (2021) — what to look for

### A01 Broken Access Control
- Every endpoint: is authentication required? Is authorization (role/scope/ownership) checked?
- Horizontal privilege escalation: can user A access user B's resource by guessing an ID? (`GET /orders/123` — verify `orders.user_id === current_user.id`).
- Default to deny. `#[PublicResource]` should be explicit and rare.
- IDs: prefer opaque/UUID over sequential integers for publicly exposed resources when enumeration matters.

### A02 Cryptographic Failures
- Passwords: **only** `password_hash($pw, PASSWORD_ARGON2ID)` + `password_verify`. Never MD5/SHA1/SHA256 directly.
- Secrets (API keys, JWT secrets, DB creds): never in code. Only in `.env`. Never committed.
- TLS everywhere in production. No HTTP in prod.
- Don't log raw secrets. Use a `SensitiveDataFilter` (the library has one) to redact `password`, `token`, `authorization`, `api_key`.

### A03 Injection
- **SQL**: PDO with `?` or `:name` parameters. **Never** string interpolation. Even for ORDER BY columns — allowlist.
- **Command**: never pass user input to `exec`, `shell_exec`, `system`, `passthru`, `popen`. If truly unavoidable, use `escapeshellarg()` and prefer a language binding over shelling out.
- **LDAP/XPath**: escape per-protocol.
- **Template injection**: don't `eval` or `include` user-controlled paths.

### A04 Insecure Design
- Rate-limit authentication endpoints (login, signup, password reset). 5 attempts per 15 minutes per IP is a sane default.
- Don't leak user enumeration: login failures say "invalid credentials", not "user not found" vs "wrong password".
- Don't leak existence via response timing. Use constant-time comparison (`hash_equals`) for token checks.

### A05 Security Misconfiguration
- Production: `display_errors=Off`, `log_errors=On`.
- Security headers (the library does this by default):
  - `X-Content-Type-Options: nosniff`
  - `X-Frame-Options: DENY`
  - `Referrer-Policy: strict-origin-when-cross-origin`
  - `Strict-Transport-Security: max-age=31536000; includeSubDomains` (only on HTTPS)
  - `Permissions-Policy: ...` (restrict camera, geolocation, etc.)
  - `Content-Security-Policy: ...` (tight for API; usually `default-src 'none'; frame-ancestors 'none'`)
- CORS: specific origins, never `*` with credentials.
- Disable unused features (`expose_php=Off` in PHP INI).
- Verbose errors only in dev. Prod errors: generic message + `requestId` for support correlation.

### A06 Vulnerable and Outdated Components
- `composer audit` regularly.
- Pin versions loosely (`^` within a major) but review lockfile updates.
- Remove unused deps — smaller attack surface.
- Monitor for CVEs in `firebase/php-jwt`, `monolog`, `vlucas/phpdotenv`.

### A07 Identification & Authentication Failures
- JWT:
  - Short-lived access tokens (15 min).
  - Refresh tokens with rotation and theft detection (reuse of an already-rotated refresh token = invalidate the whole family).
  - Store refresh tokens server-side (or hash of them) so they can be revoked.
  - Algorithm: RS256 or ES256 preferred; HS256 only with a strong secret. **Never** `alg: none`.
  - Validate `iss`, `aud`, `exp`, `nbf`, `iat` on every request. Don't just decode.
- Password reset: time-limited one-use tokens, emailed to a verified address.
- MFA for sensitive accounts.
- Account lockout / progressive delay on repeated failed logins.
- Session/JWT on logout: blacklist or rotate.

### A08 Software and Data Integrity Failures
- Never `unserialize()` untrusted data (PHP object injection).
- Verify signatures on uploaded artifacts.
- Subresource Integrity on CDN-hosted JS (if serving HTML — less relevant for pure APIs).

### A09 Security Logging and Monitoring Failures
- Log authentication failures, authorization failures, input validation failures, and all 5xx responses.
- Every log line should have a `requestId` to correlate across layers.
- Redact sensitive fields. The library's `SensitiveDataFilter` handles this — use it.
- Do not log: passwords, tokens, full credit card numbers, API keys, full authorization headers.
- Retain logs long enough for incident response (30-90 days typical).

### A10 Server-Side Request Forgery (SSRF)
- If the API fetches URLs (e.g., webhook, image import), validate the target:
  - Allowlist of domains, or
  - Block private IPs (`10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`, `127.0.0.0/8`, `169.254.0.0/16`, IPv6 equivalents).
  - Disable following redirects, or re-validate after each redirect.
  - Limit response size.

## Input validation

### Validate at the boundary, sanitize at the edge, escape at output

The library does most of this via attributes (`#[Required]`, `#[Email]`, `#[MaxLength]`, `#[Unique]`). When adding a new input field:
- What's the type? Enforce it.
- What's the length/range? Enforce it.
- Is it an email/URL/UUID? Use format validation.
- Is it a whitelist of values? Use an enum.
- Could it contain HTML that will be rendered somewhere? Sanitize or reject.

### Null byte & control char injection
User input with `\0`, `\r`, `\n` can break headers, log formats, or path handling. The library strips nulls automatically — for headers, reject any CR/LF.

### Unicode/normalization
- Normalize user identifiers (email, username) with `mb_strtolower` and/or `Normalizer::normalize($s, Normalizer::FORM_C)` before comparison.
- Be aware of homoglyph attacks on usernames (`admin` vs `аdmin` with Cyrillic `а`).

## File uploads — the danger zone

```php
$file = $this->getUploadedFile('document');
$file->isValid();                                              // upload succeeded
$file->validateType(['application/pdf', 'image/jpeg']);        // finfo check, not client-reported
$file->validateMaxSize(5 * 1024 * 1024);                      // cap size
$path = $file->moveTo('uploads/' . uniqid() . '.' . $file->extension());
```

Rules:
- **Validate MIME with `finfo`**, not with the client's `Content-Type`.
- **Validate size** — both in PHP and at the web server (`client_max_body_size` in Nginx, `LimitRequestBody` in Apache, `post_max_size`/`upload_max_filesize` in PHP INI).
- **Generate a new filename** — never trust the user's. Use `uniqid()` or a UUID.
- **Strip path components** from the filename. No `../`. The library sanitizes.
- **Store outside the web root** when possible. If inside, configure the server not to execute `.php` in that directory.
- **For images**: re-encode (load + save) to strip EXIF and any embedded payload. Cap dimensions.
- **For PDFs/Office docs**: scan for macros if the business context warrants (antivirus integration).
- **Don't serve user uploads from the same origin** that sets auth cookies (stored-XSS via HTML uploads).

## SQL injection — zero tolerance

Even in scaffolding code, even in admin endpoints, even in tests — always parameterized. The library's Query Builder does this correctly. The escape hatch is:

```php
// GOOD
Connection::getInstance()->query('SELECT * FROM users WHERE email = ?', [$email]);

// CATASTROPHIC
Connection::getInstance()->query("SELECT * FROM users WHERE email = '{$email}'");
```

Grep red flags during audit:
```
rg "->query\(\"[^\"]*\\\$" src/ tests/
rg "->execute\(\"[^\"]*\\\$" src/ tests/
rg '\$sql \.=' src/ tests/
```

## XSS — even for JSON APIs

A pure JSON API is largely immune to XSS, but:
- If error messages can contain user-supplied strings and a browser renders them (via a debug console), still escape before logging as HTML.
- If any endpoint returns HTML (e.g., Swagger UI) — ensure it doesn't render user-controlled strings unescaped.
- `Content-Type: application/json; charset=utf-8` explicitly, so browsers don't sniff-and-render.
- `X-Content-Type-Options: nosniff`.

## CSRF

Pure token-bearer APIs (Authorization header) are not vulnerable to classic CSRF. Cookies are. If the library ever supports cookie auth:
- Use `SameSite=Lax` (or `Strict` for sensitive endpoints).
- Double-submit token, or synchronizer token.
- Never use GET for state changes.

## JWT — specific gotchas

```php
// GOOD
$payload = JWT::decode($token, new Key($publicKey, 'RS256'));
// verify claims:
if ($payload->iss !== $expectedIssuer) { throw ... }
if ($payload->aud !== $expectedAudience) { throw ... }
// $exp, $nbf enforced by JWT::decode — but double-check the library does so
```

Red flags:
- Accepting `alg: none`. Never.
- Using symmetric `HS256` with a short secret.
- Not validating `aud`/`iss`.
- Trusting `alg` from the token header without pinning expected alg.
- Logging the full JWT — it's a bearer credential.
- Refresh tokens that don't rotate.

## CORS — concrete config

```php
// Allow only known origins
$allowed = explode(',', $_ENV['CORS_ALLOWED_ORIGINS'] ?? '');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header('Vary: Origin');
    header('Access-Control-Allow-Credentials: true');
}
```

Never `*` with credentials. Always echo a specific origin and `Vary: Origin`.

## Secrets management

- `.env` not committed. `.env.example` with placeholders IS committed.
- Production: use the orchestrator's secret store (AWS Secrets Manager, Vault, K8s Secret). Don't bake secrets into Docker images.
- Rotate periodically. Have a rotation plan.

## Dependency auditing

```bash
composer audit
composer outdated --direct
```

For CI: fail the build if `composer audit` returns advisories.

## Security headers cheat sheet (what the library already sets)

```
Strict-Transport-Security: max-age=31536000; includeSubDomains
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: geolocation=(), microphone=(), camera=()
Content-Security-Policy: default-src 'none'; frame-ancestors 'none'
```

## Audit checklist for a change

- [ ] All user input validated (type, length, format, allowlist).
- [ ] All SQL parameterized.
- [ ] Authentication required (or `#[PublicResource]` is deliberate).
- [ ] Authorization enforced (ownership, role, scope — not just authenticated).
- [ ] No secrets in code or logs.
- [ ] Error responses don't leak internals (stack traces, SQL, file paths).
- [ ] Rate limits on sensitive endpoints (login, signup, reset, high-cost ops).
- [ ] File uploads: MIME via finfo, size cap, sanitized filename, stored safely.
- [ ] JWT: short-lived, validated claims, `alg` pinned, refresh rotated.
- [ ] CORS: specific origins, not `*` with credentials.
- [ ] Security headers present.
- [ ] New dependencies audited (`composer audit`).
- [ ] Tests cover the happy path AND the attack paths (bad auth, wrong tenant, injection attempts).
