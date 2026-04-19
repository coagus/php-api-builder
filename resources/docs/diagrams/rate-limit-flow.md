# Rate Limit Flow

```mermaid
flowchart TD
    Start([Request enters RateLimitMiddleware]) --> Ip[Resolve client IP<br/>X-Forwarded-For or REMOTE_ADDR]
    Ip --> Key[Build key: ratelimit:&lt;ip&gt;]
    Key --> Hit[[RateLimitStore::hit key, windowSeconds]]
    Hit --> Load[Open file with flock LOCK_EX]
    Load --> Check{data null<br/>or reset_at &lt;= now?}
    Check -- yes --> Reset[count = 1<br/>reset_at = now + window]
    Check -- no --> Inc[count++]
    Reset --> Persist[Write JSON, release lock]
    Inc --> Persist
    Persist --> Evaluate{count &gt; limit?}
    Evaluate -- yes --> Block[Response 429<br/>RFC 7807 body<br/>Retry-After = reset_at - now]
    Evaluate -- no --> Next[Call next request]
    Next --> OkResp[Response from handler]
    Block --> Headers[Add X-RateLimit-Limit,<br/>X-RateLimit-Remaining,<br/>X-RateLimit-Reset]
    OkResp --> Headers
    Headers --> Done([Response returned])
```

**Figure 5 — Rate limit flow.** The middleware keys counters by client IP (honouring the first `X-Forwarded-For` hop when present) and persists them to `sys_get_temp_dir() . '/php-api-builder-ratelimit'` with an exclusive file lock, so the mechanism works without Redis or Memcached. Limits default to 60 requests per 60 seconds and are configurable via `RATE_LIMIT_MAX` and `RATE_LIMIT_WINDOW` in `.env`. Every response — allowed or blocked — carries the standard `X-RateLimit-*` headers, and 429 responses add `Retry-After` with the remaining seconds in the window. See `src/Http/Middleware/RateLimitMiddleware.php` and `src/Http/Middleware/RateLimitStore.php`.
