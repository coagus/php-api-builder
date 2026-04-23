<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder;

use Coagus\PhpApiBuilder\Attributes\Route;
use Coagus\PhpApiBuilder\Helpers\Utils;
use Coagus\PhpApiBuilder\ORM\Entity;
use Coagus\PhpApiBuilder\Resource\APIDB;
use Coagus\PhpApiBuilder\Resource\Resource;
use Coagus\PhpApiBuilder\Resource\Service;
use ReflectionClass;

class Router
{
    private string $namespace;
    private string $apiPrefix;

    public function __construct(string $namespace, string $apiPrefix = '/api/v1')
    {
        $this->namespace = rtrim($namespace, '\\');
        $this->apiPrefix = $apiPrefix;
    }

    public function resolve(string $method, string $path): ?array
    {
        $parsed = $this->parsePath($path);
        if ($parsed === null) {
            return null;
        }

        $resourceName = $parsed['resource'];
        $resourceId = $parsed['id'];
        $action = $parsed['action'];

        $class = $this->discoverClass($resourceName);
        if ($class === null) {
            return null;
        }

        $methodName = $this->resolveMethod($method, $action, $class);
        if ($methodName === null) {
            return ['error' => 'method_not_allowed', 'method' => $method];
        }

        return [
            'class' => $class,
            'method' => $methodName,
            'resourceId' => $resourceId,
            'action' => $action,
            'resourceName' => $resourceName,
        ];
    }

    public function parsePath(string $path): ?array
    {
        $path = rtrim($path, '/');

        if (!str_starts_with($path, $this->apiPrefix)) {
            return null;
        }

        $relative = substr($path, strlen($this->apiPrefix));
        $relative = ltrim($relative, '/');

        if ($relative === '') {
            return null;
        }

        $segments = explode('/', $relative);
        $resource = $segments[0];
        $second = $segments[1] ?? null;
        $third = $segments[2] ?? null;

        if ($second === null) {
            return ['resource' => $resource, 'id' => null, 'action' => null];
        }

        // Numeric 2nd segment: /users/42 or /users/42/orders
        if (is_numeric($second)) {
            return ['resource' => $resource, 'id' => $second, 'action' => $third];
        }

        // Non-numeric 2nd segment is an action; 3rd (if any) is the id for it.
        // e.g. /me/sessions/123 → action=sessions, id=123 (UI-005 fix).
        return ['resource' => $resource, 'id' => $third, 'action' => $second];
    }

    public function resolveMethod(string $httpMethod, ?string $action, string $class): ?string
    {
        $httpMethod = strtolower($httpMethod);

        if ($action !== null) {
            // Custom action: POST /users/login → postLogin()
            $actionCamel = Utils::snakeToCamel(str_replace('-', '_', $action));
            $customMethod = $httpMethod . ucfirst($actionCamel);
            if (method_exists($class, $customMethod)) {
                return $customMethod;
            }

            return null;
        }

        // Standard CRUD method
        if (method_exists($class, $httpMethod)) {
            return $httpMethod;
        }

        // Entity classes get wrapped in APIDB which provides all CRUD methods
        if (is_subclass_of($class, Entity::class)) {
            $crudMethods = ['get', 'post', 'put', 'patch', 'delete'];
            if (in_array($httpMethod, $crudMethods, true)) {
                return $httpMethod;
            }
        }

        return null;
    }

    private function discoverClass(string $resourceName): ?string
    {
        $className = $this->resourceNameToClassName($resourceName);

        // 1. Check for Service class
        $serviceClass = $this->namespace . '\\' . $className;
        if (class_exists($serviceClass)) {
            return $serviceClass;
        }

        // 2. Check for Entity class in entities subdirectory
        $entityClass = $this->namespace . '\\Entities\\' . $className;
        if (class_exists($entityClass) && is_subclass_of($entityClass, Entity::class)) {
            return $entityClass;
        }

        return null;
    }

    private function resourceNameToClassName(string $resourceName): string
    {
        // Convert kebab-case URL resource to PascalCase class name
        // user-profiles → UserProfile (singular)
        $parts = explode('-', $resourceName);
        $pascal = implode('', array_map('ucfirst', $parts));

        // Try to singularize (simple heuristic)
        if (str_ends_with($pascal, 'ies')) {
            return substr($pascal, 0, -3) . 'y';
        }
        if (str_ends_with($pascal, 'ses') || str_ends_with($pascal, 'xes') || str_ends_with($pascal, 'shes') || str_ends_with($pascal, 'ches')) {
            return substr($pascal, 0, -2);
        }
        if (str_ends_with($pascal, 's') && !str_ends_with($pascal, 'ss')) {
            return substr($pascal, 0, -1);
        }

        return $pascal;
    }

    public function isEntityResource(string $class): bool
    {
        return is_subclass_of($class, Entity::class);
    }

    public function isServiceResource(string $class): bool
    {
        return is_subclass_of($class, Service::class) || is_subclass_of($class, APIDB::class);
    }
}
