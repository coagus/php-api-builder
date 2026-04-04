<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\Http\Middleware;

use Coagus\PhpApiBuilder\Http\Request;
use Coagus\PhpApiBuilder\Http\Response;

class MiddlewarePipeline
{
    private array $middlewares = [];

    public function pipe(MiddlewareInterface $middleware): static
    {
        $this->middlewares[] = $middleware;

        return $this;
    }

    public function process(Request $request, callable $handler): Response
    {
        $pipeline = array_reduce(
            array_reverse($this->middlewares),
            function (callable $next, MiddlewareInterface $middleware): callable {
                return function (Request $request) use ($middleware, $next): Response {
                    return $middleware->handle($request, $next);
                };
            },
            $handler
        );

        return $pipeline($request);
    }
}
