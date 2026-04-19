# Authentication — Login and Authenticated Request

```mermaid
sequenceDiagram
    autonumber
    participant C as Client
    participant API as API (APIDB / Service)
    participant MW as AuthMiddleware
    participant JWT as Auth (firebase/php-jwt)
    participant DB as Database

    Note over C,DB: Login flow — public route, no JWT expected
    C->>API: POST /api/v1/users/login { email, password }
    API->>MW: pipeline.process(request)
    MW-->>API: pass (path matches publicPaths)
    API->>DB: SELECT * FROM users WHERE email = ? LIMIT 1
    DB-->>API: user row (or null)
    alt credentials invalid
        API-->>C: 401 Unauthorized (application/problem+json)
    else credentials valid
        API->>JWT: generateAccessToken(userData, scopes)
        JWT-->>API: access token (exp = now + access_ttl)
        API->>JWT: generateRefreshToken(userId)
        JWT-->>API: refresh token (exp = now + refresh_ttl)
        API-->>C: 200 { accessToken, refreshToken }
    end

    Note over C,DB: Authenticated request — Bearer token required
    C->>API: GET /api/v1/posts\nAuthorization: Bearer <access>
    API->>MW: pipeline.process(request)
    MW->>JWT: validateToken(access)
    alt token invalid or expired
        JWT-->>MW: RuntimeException
        MW-->>C: 401 Unauthorized (RFC 7807)
    else token is refresh type
        MW-->>C: 401 Unauthorized (refresh cannot access API)
    else token valid
        JWT-->>MW: decoded payload { sub, scopes, data }
        MW-->>API: next(request)
        API->>DB: SELECT ... FROM posts
        DB-->>API: rows
        API-->>C: 200 { data, meta } + X-Request-ID
    end
```

**Figure 3 — Login and authenticated request.** Login endpoints are exempted by registering their path in `AuthMiddleware`'s `publicPaths`. On success the library issues a short-lived access token (default 15 minutes, `access_ttl`) and a longer refresh token (default 7 days, `refresh_ttl`) signed with the configured algorithm — HS256 by default, RS256/ES* if `private_key`/`public_key` are supplied. Every non-public request must present the access token as `Authorization: Bearer <jwt>`; refresh tokens are explicitly rejected for API access. See `src/Auth/Auth.php` and `src/Http/Middleware/AuthMiddleware.php`.
