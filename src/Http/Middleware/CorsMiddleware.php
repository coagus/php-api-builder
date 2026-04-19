<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\Http\Middleware;

use Coagus\PhpApiBuilder\Http\Request;
use Coagus\PhpApiBuilder\Http\Response;
use RuntimeException;

class CorsMiddleware implements MiddlewareInterface
{
    private const DEFAULT_ORIGINS = 'http://localhost:8080';
    private const DEFAULT_METHODS = 'GET,POST,PUT,PATCH,DELETE,OPTIONS';
    private const DEFAULT_HEADERS = 'Content-Type,Authorization,X-Request-ID';
    private const DEFAULT_MAX_AGE = '86400';
    private const WILDCARD_ORIGIN = '*';

    private string $allowedOrigins;
    private string $allowedMethods;
    private string $allowedHeaders;
    private string $maxAge;
    private bool $allowCredentials;

    public function __construct(
        ?string $allowedOrigins = null,
        ?string $allowedMethods = null,
        ?string $allowedHeaders = null,
        ?string $maxAge = null,
        ?bool $allowCredentials = null
    ) {
        $this->allowedOrigins = $allowedOrigins ?? ($_ENV['CORS_ALLOWED_ORIGINS'] ?? self::DEFAULT_ORIGINS);
        $this->allowedMethods = $allowedMethods ?? ($_ENV['CORS_ALLOWED_METHODS'] ?? self::DEFAULT_METHODS);
        $this->allowedHeaders = $allowedHeaders ?? ($_ENV['CORS_ALLOWED_HEADERS'] ?? self::DEFAULT_HEADERS);
        $this->maxAge = $maxAge ?? ($_ENV['CORS_MAX_AGE'] ?? self::DEFAULT_MAX_AGE);
        $this->allowCredentials = $allowCredentials ?? (($_ENV['CORS_ALLOW_CREDENTIALS'] ?? 'false') === 'true');

        $this->assertValidConfiguration();
    }

    public function handle(Request $request, callable $next): Response
    {
        if ($request->getMethod() === 'OPTIONS') {
            $response = new Response(null, 204);
        } else {
            $response = $next($request);
        }

        $origin = $request->getHeader('Origin') ?? '';
        $allowHeader = $this->resolveAllowOriginHeader($origin);
        if ($allowHeader !== null) {
            $response->header('Access-Control-Allow-Origin', $allowHeader);
            // When responses may vary by origin, tell caches to key on Origin.
            $response->header('Vary', 'Origin');
        }

        $response->header('Access-Control-Allow-Methods', $this->allowedMethods);
        $response->header('Access-Control-Allow-Headers', $this->allowedHeaders);
        $response->header('Access-Control-Max-Age', $this->maxAge);

        if ($this->allowCredentials) {
            $response->header('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }

    private function resolveAllowOriginHeader(string $origin): ?string
    {
        if ($this->allowedOrigins === self::WILDCARD_ORIGIN) {
            // Wildcard is only reachable here when credentials are NOT allowed
            // (the constructor rejects that combo). Echo the request Origin so
            // downstream caches/proxies can key on it correctly; fall back to
            // "*" when no Origin header was sent.
            return $origin !== '' ? $origin : self::WILDCARD_ORIGIN;
        }

        $allowed = array_map('trim', explode(',', $this->allowedOrigins));
        if (in_array($origin, $allowed, true) && $origin !== '') {
            return $origin;
        }

        return null;
    }

    private function assertValidConfiguration(): void
    {
        if ($this->allowedOrigins === self::WILDCARD_ORIGIN && $this->allowCredentials) {
            throw new RuntimeException(
                'CORS misconfiguration: wildcard origin ("*") cannot be combined with allowCredentials=true. '
                . 'Specify an explicit comma-separated list in CORS_ALLOWED_ORIGINS.'
            );
        }
    }
}
