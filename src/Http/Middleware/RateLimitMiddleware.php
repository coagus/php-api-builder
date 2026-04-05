<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\Http\Middleware;

use Coagus\PhpApiBuilder\Helpers\ApiResponse;
use Coagus\PhpApiBuilder\Http\Request;
use Coagus\PhpApiBuilder\Http\Response;

class RateLimitMiddleware implements MiddlewareInterface
{
    private int $limit;
    private int $windowSeconds;
    private RateLimitStore $store;

    public function __construct(
        ?int $limit = null,
        ?int $windowSeconds = null,
        ?RateLimitStore $store = null
    ) {
        $this->limit = $limit ?? (int) ($_ENV['RATE_LIMIT_MAX'] ?? 60);
        $this->windowSeconds = $windowSeconds ?? (int) ($_ENV['RATE_LIMIT_WINDOW'] ?? 60);
        $this->store = $store ?? new RateLimitStore();
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
        $forwarded = $request->getHeader('X-Forwarded-For');

        if ($forwarded !== null && $forwarded !== '') {
            return trim(explode(',', $forwarded)[0]);
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}
