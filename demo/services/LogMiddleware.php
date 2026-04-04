<?php

declare(strict_types=1);

namespace DemoApi;

use Coagus\PhpApiBuilder\Http\Middleware\MiddlewareInterface;
use Coagus\PhpApiBuilder\Http\Request;
use Coagus\PhpApiBuilder\Http\Response;

class LogMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $start = microtime(true);

        $response = $next($request);

        $duration = round((microtime(true) - $start) * 1000, 2);
        $response->header('X-Response-Time', "{$duration}ms");

        return $response;
    }
}
