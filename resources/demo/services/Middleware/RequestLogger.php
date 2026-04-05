<?php

declare(strict_types=1);

namespace App\Middleware;

use Coagus\PhpApiBuilder\Http\Middleware\MiddlewareInterface;
use Coagus\PhpApiBuilder\Http\Request;
use Coagus\PhpApiBuilder\Http\Response;

class RequestLogger implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $start = microtime(true);

        $response = $next($request);

        $duration = round((microtime(true) - $start) * 1000, 2);
        $method = $request->getMethod();
        $path = $request->getPath();
        $status = $response->getStatusCode();

        error_log("[{$method}] {$path} → {$status} ({$duration}ms)");

        return $response;
    }
}
