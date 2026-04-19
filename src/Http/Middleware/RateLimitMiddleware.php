<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\Http\Middleware;

use Coagus\PhpApiBuilder\Helpers\ApiResponse;
use Coagus\PhpApiBuilder\Http\Request;
use Coagus\PhpApiBuilder\Http\Response;

class RateLimitMiddleware implements MiddlewareInterface
{
    private const DEFAULT_LIMIT = 60;
    private const DEFAULT_WINDOW_SECONDS = 60;
    private const FALLBACK_IP = '127.0.0.1';

    private int $limit;
    private int $windowSeconds;
    private RateLimitStore $store;
    /** @var list<string> */
    private array $trustedProxies;

    /**
     * @param list<string>|null $trustedProxies IPs whose X-Forwarded-For we honor.
     *   When null, reads RATE_LIMIT_TRUSTED_PROXIES from env (comma-separated).
     *   Empty list ⇒ never trust X-Forwarded-For (single-node default).
     */
    public function __construct(
        ?int $limit = null,
        ?int $windowSeconds = null,
        ?RateLimitStore $store = null,
        ?array $trustedProxies = null,
    ) {
        $this->limit = $limit ?? (int) ($_ENV['RATE_LIMIT_MAX'] ?? self::DEFAULT_LIMIT);
        $this->windowSeconds = $windowSeconds ?? (int) ($_ENV['RATE_LIMIT_WINDOW'] ?? self::DEFAULT_WINDOW_SECONDS);
        $this->store = $store ?? new RateLimitStore();
        $this->trustedProxies = $trustedProxies ?? $this->parseTrustedProxiesFromEnv();
    }

    public function handle(Request $request, callable $next): Response
    {
        $clientIp = $this->resolveClientIp($request);
        $key = 'ratelimit:' . $clientIp;

        $data = $this->store->hit($key, $this->windowSeconds);
        $remaining = max(0, $this->limit - $data['count']);

        if ($data['count'] > $this->limit) {
            $retryAfter = $data['reset_at'] - time();
            $response = ApiResponse::error(
                'Too Many Requests',
                429,
                'Rate limit exceeded. Try again later.'
            );
            $response->header('Retry-After', (string) $retryAfter);
        } else {
            $response = $next($request);
        }

        $response->header('X-RateLimit-Limit', (string) $this->limit);
        $response->header('X-RateLimit-Remaining', (string) $remaining);
        $response->header('X-RateLimit-Reset', (string) $data['reset_at']);

        return $response;
    }

    private function resolveClientIp(Request $request): string
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? self::FALLBACK_IP;

        // Only honor X-Forwarded-For when the direct peer is a trusted proxy.
        if (!in_array($remoteAddr, $this->trustedProxies, true)) {
            return $remoteAddr;
        }

        $forwarded = $request->getHeader('X-Forwarded-For');
        if ($forwarded === null || $forwarded === '') {
            return $remoteAddr;
        }

        $first = trim(explode(',', $forwarded)[0]);

        return $first !== '' ? $first : $remoteAddr;
    }

    /**
     * @return list<string>
     */
    private function parseTrustedProxiesFromEnv(): array
    {
        $raw = $_ENV['RATE_LIMIT_TRUSTED_PROXIES'] ?? '';
        if ($raw === '') {
            return [];
        }

        $values = array_map('trim', explode(',', $raw));

        return array_values(array_filter($values, static fn(string $ip): bool => $ip !== ''));
    }
}
