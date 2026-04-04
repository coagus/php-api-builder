<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\Http\Middleware;

use Coagus\PhpApiBuilder\Auth\ApiKeyAuth;
use Coagus\PhpApiBuilder\Auth\Auth;
use Coagus\PhpApiBuilder\Helpers\ApiResponse;
use Coagus\PhpApiBuilder\Http\Request;
use Coagus\PhpApiBuilder\Http\Response;

class AuthMiddleware implements MiddlewareInterface
{
    private array $publicPaths;

    public function __construct(array $publicPaths = [])
    {
        $this->publicPaths = $publicPaths;
    }

    public function handle(Request $request, callable $next): Response
    {
        // Skip auth for OPTIONS (CORS preflight)
        if ($request->getMethod() === 'OPTIONS') {
            return $next($request);
        }

        // Check if path is public
        if ($this->isPublicPath($request->getPath())) {
            return $next($request);
        }

        // Try API Key auth first
        if (ApiKeyAuth::isConfigured() && ApiKeyAuth::validate($request)) {
            return $next($request);
        }

        // JWT Bearer token
        $token = $request->getBearerToken();
        if ($token === null) {
            return ApiResponse::error('Unauthorized', 401, 'Missing authentication token.');
        }

        try {
            $decoded = Auth::validateToken($token);

            // Check if it's not a refresh token
            if (isset($decoded->type) && $decoded->type === 'refresh') {
                return ApiResponse::error('Unauthorized', 401, 'Refresh tokens cannot be used for API access.');
            }

            return $next($request);
        } catch (\RuntimeException $e) {
            return ApiResponse::error('Unauthorized', 401, $e->getMessage());
        }
    }

    private function isPublicPath(string $path): bool
    {
        foreach ($this->publicPaths as $publicPath) {
            if (str_starts_with($path, $publicPath)) {
                return true;
            }
        }

        return false;
    }
}
