<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder;

use Coagus\PhpApiBuilder\Helpers\ErrorHandler;
use Coagus\PhpApiBuilder\Http\Middleware\MiddlewareInterface;
use Coagus\PhpApiBuilder\Http\Middleware\MiddlewarePipeline;
use Coagus\PhpApiBuilder\Http\Request;
use Coagus\PhpApiBuilder\Http\Response;
use Coagus\PhpApiBuilder\ORM\Entity;
use Coagus\PhpApiBuilder\Resource\APIDB;
use Coagus\PhpApiBuilder\Resource\Resource;

class API
{
    private Router $router;
    private array $middlewareClasses = [];
    private string $requestId;

    public function __construct(
        private readonly string $namespace,
        private readonly string $apiPrefix = '/api/v1'
    ) {
        $this->router = new Router($namespace, $apiPrefix);
        $this->requestId = $this->generateRequestId();
    }

    public function middleware(array $classes): static
    {
        $this->middlewareClasses = $classes;

        return $this;
    }

    public function run(?Request $request = null): Response
    {
        $request = $request ?? new Request();

        try {
            $pipeline = new MiddlewarePipeline();
            foreach ($this->middlewareClasses as $middlewareClass) {
                $middleware = is_string($middlewareClass) ? new $middlewareClass() : $middlewareClass;
                if ($middleware instanceof MiddlewareInterface) {
                    $pipeline->pipe($middleware);
                }
            }

            $response = $pipeline->process($request, function (Request $req) {
                return $this->dispatch($req);
            });

            $response->header('X-Request-ID', $this->requestId);

            return $response;
        } catch (\Throwable $e) {
            $response = ErrorHandler::handle($e, $this->requestId);
            $response->header('X-Request-ID', $this->requestId);

            return $response;
        }
    }

    public function getRouter(): Router
    {
        return $this->router;
    }

    private function dispatch(Request $request): Response
    {
        $result = $this->router->resolve($request->getMethod(), $request->getPath());

        if ($result === null) {
            return ErrorHandler::notFound(
                "The resource was not found.",
                $request->getPath()
            );
        }

        if (isset($result['error']) && $result['error'] === 'method_not_allowed') {
            return ErrorHandler::methodNotAllowed($result['method'], $request->getPath());
        }

        $class = $result['class'];
        $method = $result['method'];
        $resourceId = $result['resourceId'];
        $action = $result['action'];

        // Determine handler
        if (is_subclass_of($class, Resource::class)) {
            // Service or APIDB subclass
            $handler = new $class();
        } elseif (is_subclass_of($class, Entity::class)) {
            // Plain Entity → wrap in generic APIDB
            $handler = new class($class) extends APIDB {
                public function __construct(string $entityClass)
                {
                    $this->entity = $entityClass;
                }
            };
        } else {
            return ErrorHandler::notFound("The resource was not found.", $request->getPath());
        }

        $handler->setRequest($request);
        $handler->setResourceId($resourceId);
        $handler->setAction($action);

        if (!method_exists($handler, $method)) {
            return ErrorHandler::methodNotAllowed($request->getMethod(), $request->getPath());
        }

        $handler->{$method}();

        $response = $handler->getResponse();
        if ($response === null) {
            return new Response(['data' => null], 200);
        }

        return $response;
    }

    private function generateRequestId(): string
    {
        return bin2hex(random_bytes(8));
    }
}
