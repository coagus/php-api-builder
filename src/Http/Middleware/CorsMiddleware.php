<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\Http\Middleware;

use Coagus\PhpApiBuilder\Http\Request;
use Coagus\PhpApiBuilder\Http\Response;

class CorsMiddleware implements MiddlewareInterface
{
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
        $this->allowedOrigins = $allowedOrigins ?? ($_ENV['CORS_ALLOWED_ORIGINS'] ?? '*');
        $this->allowedMethods = $allowedMethods ?? ($_ENV['CORS_ALLOWED_METHODS'] ?? 'GET,POST,PUT,PATCH,DELETE,OPTIONS');
        $this->allowedHeaders = $allowedHeaders ?? ($_ENV['CORS_ALLOWED_HEADERS'] ?? 'Content-Type,Authorization,X-Request-ID');
        $this->maxAge = $maxAge ?? ($_ENV['CORS_MAX_AGE'] ?? '86400');
        $this->allowCredentials = $allowCredentials ?? (($_ENV['CORS_ALLOW_CREDENTIALS'] ?? 'false') === 'true');
    }

    public function handle(Request $request, callable $next): Response
    {
        if ($request->getMethod() === 'OPTIONS') {
            $response = new Response(null, 204);
        } else {
            $response = $next($request);
        }

        $origin = $request->getHeader('Origin') ?? '';

        if ($this->isOriginAllowed($origin)) {
            $response->header('Access-Control-Allow-Origin', $origin ?: $this->allowedOrigins);
        }

        $response->header('Access-Control-Allow-Methods', $this->allowedMethods);
        $response->header('Access-Control-Allow-Headers', $this->allowedHeaders);
        $response->header('Access-Control-Max-Age', $this->maxAge);

        if ($this->allowCredentials) {
            $response->header('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }

    private function isOriginAllowed(string $origin): bool
    {
        if ($this->allowedOrigins === '*') {
            return true;
        }

        $allowed = array_map('trim', explode(',', $this->allowedOrigins));

        return in_array($origin, $allowed, true);
    }
}
