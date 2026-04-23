<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder;

use Coagus\PhpApiBuilder\Helpers\ErrorHandler;
use Coagus\PhpApiBuilder\Http\Middleware\MiddlewareInterface;
use Coagus\PhpApiBuilder\Http\Middleware\MiddlewarePipeline;
use Coagus\PhpApiBuilder\Http\Middleware\MiddlewareResolver;
use Coagus\PhpApiBuilder\Http\Request;
use Coagus\PhpApiBuilder\Http\Response;
use Coagus\PhpApiBuilder\OpenAPI\DocsController;
use Coagus\PhpApiBuilder\OpenAPI\SpecBuilder;
use Coagus\PhpApiBuilder\ORM\Entity;
use Coagus\PhpApiBuilder\Resource\APIDB;
use Coagus\PhpApiBuilder\Resource\Resource;

class API
{
    private Router $router;
    private array $middlewareClasses = [];
    private string $requestId;
    /** @var array<string, array{0: class-string, 1: string}> */
    private readonly array $wellKnownRoutes;

    /**
     * @param array<string, array{0: class-string, 1: string}> $wellKnown
     *     Map of raw request paths (outside $apiPrefix) to [class, method] tuples.
     *     Consulted before the Router, enabling RFC 8615 well-known URLs such as
     *     `/.well-known/jwks.json` and OpenID Connect discovery documents.
     *
     * @throws \InvalidArgumentException if any tuple is malformed, the class does
     *     not exist, or the method is not callable on an instance of that class.
     */
    public function __construct(
        private readonly string $namespace,
        private readonly string $apiPrefix = '/api/v1',
        array $wellKnown = []
    ) {
        $this->wellKnownRoutes = self::normalizeWellKnown($wellKnown);
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
        // Well-known routes (RFC 8615) — consulted before apiPrefix matching.
        $wellKnownResponse = $this->handleWellKnown($request);
        if ($wellKnownResponse !== null) {
            return $wellKnownResponse;
        }

        // Built-in docs route
        $docsResponse = $this->handleDocs($request);
        if ($docsResponse !== null) {
            return $docsResponse;
        }

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

        return $this->runThroughRouteMiddlewares(
            $class,
            $method,
            $request,
            fn(Request $req) => $this->invokeHandler($handler, $method)
        );
    }

    private function runThroughRouteMiddlewares(
        string $class,
        string $method,
        Request $request,
        callable $terminal
    ): Response {
        $middlewares = MiddlewareResolver::resolveFor($class, $method);
        if ($middlewares === []) {
            return $terminal($request);
        }

        $pipeline = new MiddlewarePipeline();
        foreach ($middlewares as $middleware) {
            $pipeline->pipe($middleware);
        }

        return $pipeline->process($request, $terminal);
    }

    private function invokeHandler(object $handler, string $method): Response
    {
        $handler->{$method}();

        $response = $handler->getResponse();
        if ($response === null) {
            return new Response(['data' => null], 200);
        }

        return $response;
    }

    private function handleWellKnown(Request $request): ?Response
    {
        if ($this->wellKnownRoutes === []) {
            return null;
        }

        $path = rtrim($request->getPath(), '/');
        if ($path === '') {
            $path = '/';
        }

        if (!isset($this->wellKnownRoutes[$path])) {
            return null;
        }

        [$class, $method] = $this->wellKnownRoutes[$path];
        $handler = new $class();

        if ($handler instanceof Resource) {
            $handler->setRequest($request);
            $handler->setResourceId(null);
            $handler->setAction(null);

            return $this->invokeHandler($handler, $method);
        }

        $result = $handler->{$method}($request);

        return $result instanceof Response
            ? $result
            : new Response(['data' => $result], 200);
    }

    private function handleDocs(Request $request): ?Response
    {
        if ($request->getMethod() !== 'GET') {
            return null;
        }

        $path = rtrim($request->getPath(), '/');
        $docsPrefix = $this->apiPrefix . '/docs';

        if ($path !== $docsPrefix && !str_starts_with($path, "{$docsPrefix}/")) {
            return null;
        }

        $specBuilder = new SpecBuilder($this->namespace, '1.0.0', $this->apiPrefix);
        $this->discoverEntities($specBuilder);
        $this->discoverServices($specBuilder);
        $handler = new DocsController($specBuilder);
        $handler->setRequest($request);

        $action = substr($path, strlen($docsPrefix) + 1) ?: null;
        $handler->setAction($action);
        $handler->get();

        return $handler->getResponse();
    }

    private function discoverEntities(SpecBuilder $specBuilder): void
    {
        $entitiesNamespace = $this->namespace . '\\Entities\\';

        // Find entity directory from PSR-4 autoload
        $autoloadPaths = [
            getcwd() . '/entities',
            getcwd() . '/src/Entities',
        ];

        foreach ($autoloadPaths as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            foreach (glob("{$dir}/*.php") as $file) {
                $className = $entitiesNamespace . pathinfo($file, PATHINFO_FILENAME);
                if (class_exists($className) && is_subclass_of($className, Entity::class)) {
                    $specBuilder->addEntity($className);
                }
            }
        }
    }

    private function discoverServices(SpecBuilder $specBuilder): void
    {
        $autoloadPaths = [
            getcwd() . '/services',
            getcwd() . '/src/Services',
        ];

        foreach ($autoloadPaths as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            foreach (glob("{$dir}/*.php") as $file) {
                $className = $this->namespace . '\\' . pathinfo($file, PATHINFO_FILENAME);
                if (class_exists($className) && is_subclass_of($className, Resource::class)) {
                    $specBuilder->addService($className);
                }
            }
        }
    }

    private function generateRequestId(): string
    {
        return bin2hex(random_bytes(8));
    }

    /**
     * Validate the $wellKnown map fail-fast at construction time.
     *
     * @param array<string, array{0: class-string, 1: string}> $wellKnown
     *
     * @return array<string, array{0: class-string, 1: string}>
     *
     * @throws \InvalidArgumentException
     */
    private static function normalizeWellKnown(array $wellKnown): array
    {
        $normalized = [];
        foreach ($wellKnown as $path => $tuple) {
            self::assertWellKnownEntry($path, $tuple);
            $normalized[rtrim((string) $path, '/') ?: '/'] = [$tuple[0], $tuple[1]];
        }

        return $normalized;
    }

    /**
     * @throws \InvalidArgumentException
     */
    private static function assertWellKnownEntry(mixed $path, mixed $tuple): void
    {
        if (!is_string($path) || $path === '' || $path[0] !== '/') {
            throw new \InvalidArgumentException(
                'wellKnown paths must be non-empty strings starting with "/".'
            );
        }

        if (!is_array($tuple) || !array_is_list($tuple) || count($tuple) !== 2) {
            throw new \InvalidArgumentException(
                "wellKnown['{$path}'] must be a [Class::class, 'method'] tuple."
            );
        }

        [$class, $method] = $tuple;
        if (!is_string($class) || !class_exists($class)) {
            throw new \InvalidArgumentException(
                "wellKnown['{$path}']: class '" . (is_string($class) ? $class : gettype($class)) . "' does not exist."
            );
        }

        if (!is_string($method) || !method_exists($class, $method)) {
            throw new \InvalidArgumentException(
                "wellKnown['{$path}']: method '" . (is_string($method) ? $method : gettype($method)) . "' is not callable on {$class}."
            );
        }
    }
}
