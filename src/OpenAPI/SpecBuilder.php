<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\OpenAPI;

use Coagus\PhpApiBuilder\Attributes\PublicResource;
use Coagus\PhpApiBuilder\Helpers\Utils;
use Coagus\PhpApiBuilder\Resource\APIDB;
use ReflectionClass;
use ReflectionMethod;

class SpecBuilder
{
    private string $title;
    private string $version;
    private string $apiPrefix;
    private array $entityClasses = [];
    private array $serviceClasses = [];

    public function __construct(string $title = 'API', string $version = '1.0.0', string $apiPrefix = '/api/v1')
    {
        $this->title = $title;
        $this->version = $version;
        $this->apiPrefix = $apiPrefix;
    }

    public function addEntity(string $entityClass, ?string $resourcePath = null): static
    {
        $ref = new ReflectionClass($entityClass);
        $shortName = $ref->getShortName();
        $path = $resourcePath ?? Utils::camelToSnake($shortName) . 's';
        $path = str_replace('_', '-', $path);

        $this->entityClasses[] = [
            'class' => $entityClass,
            'name' => $shortName,
            'path' => $path,
        ];

        return $this;
    }

    public function addService(string $serviceClass, ?string $resourcePath = null): static
    {
        $ref = new ReflectionClass($serviceClass);
        $shortName = $ref->getShortName();
        $path = $resourcePath ?? Utils::camelToSnake($shortName);
        $path = str_replace('_', '-', $path);

        $this->serviceClasses[] = [
            'class' => $serviceClass,
            'name' => $shortName,
            'path' => $path,
        ];

        return $this;
    }

    public function build(): array
    {
        $spec = [
            'openapi' => '3.1.0',
            'info' => [
                'title' => $this->title,
                'version' => $this->version,
            ],
            'paths' => [],
            'components' => [
                'schemas' => $this->buildCommonSchemas(),
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT',
                    ],
                ],
            ],
            'security' => [['bearerAuth' => []]],
        ];

        foreach ($this->entityClasses as $entity) {
            $paths = $this->buildEntityPaths($entity);
            $spec['paths'] = array_merge($spec['paths'], $paths);

            $schema = SchemaGenerator::generate($entity['class']);
            $spec['components']['schemas'][$entity['name']] = $schema;

            $createSchema = SchemaGenerator::generateForCreate($entity['class']);
            $spec['components']['schemas'][$entity['name'] . 'Create'] = $createSchema;
        }

        foreach ($this->serviceClasses as $service) {
            $paths = $this->buildServicePaths($service);
            foreach ($paths as $pathKey => $methods) {
                if (isset($spec['paths'][$pathKey])) {
                    $spec['paths'][$pathKey] = array_merge($spec['paths'][$pathKey], $methods);
                } else {
                    $spec['paths'][$pathKey] = $methods;
                }
            }
        }

        return $spec;
    }

    public function toJson(): string
    {
        return json_encode($this->build(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function buildServicePaths(array $service): array
    {
        $basePath = "{$this->apiPrefix}/{$service['path']}";
        $class = $service['class'];
        $isPublic = $this->isPublicResource($class);
        $isApidb = is_subclass_of($class, APIDB::class);
        $ref = new ReflectionClass($class);
        $paths = [];

        // If it's an APIDB subclass, its CRUD is already covered by entity paths.
        // Only add the standard get() for pure Services.
        if (!$isApidb) {
            $standardMethods = ['get', 'post', 'put', 'patch', 'delete'];
            foreach ($standardMethods as $httpMethod) {
                if ($ref->hasMethod($httpMethod) && $ref->getMethod($httpMethod)->getDeclaringClass()->getName() === $class) {
                    $operation = [
                        'summary' => ucfirst($httpMethod) . " {$service['path']}",
                        'tags' => [$service['name']],
                        'responses' => [
                            '200' => ['description' => 'Success'],
                        ],
                    ];

                    if ($isPublic) {
                        $operation['security'] = [];
                    }

                    $paths[$basePath][$httpMethod] = $operation;
                }
            }
        }

        // Discover custom action methods (postLogin, patchPublish, etc.)
        foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() || $method->isAbstract()) {
                continue;
            }

            // Only methods declared in this class, not inherited
            if ($method->getDeclaringClass()->getName() !== $class) {
                continue;
            }

            $methodName = $method->getName();

            if (!preg_match('/^(get|post|put|patch|delete)([A-Z].*)$/', $methodName, $matches)) {
                continue;
            }

            $httpMethod = strtolower($matches[1]);
            $action = lcfirst($matches[2]);
            $actionKebab = str_replace('_', '-', Utils::camelToSnake($action));

            $actionPath = "{$basePath}/{$actionKebab}";
            $summary = ucfirst($actionKebab) . " — {$service['name']}";

            $operation = [
                'summary' => str_replace('-', ' ', $summary),
                'tags' => [$service['name']],
                'responses' => [
                    '200' => ['description' => 'Success'],
                    '400' => ['description' => 'Bad Request'],
                    '401' => ['description' => 'Unauthorized'],
                ],
            ];

            if (in_array($httpMethod, ['post', 'put', 'patch'])) {
                $operation['requestBody'] = [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => ['type' => 'object'],
                        ],
                    ],
                ];
            }

            if ($isPublic) {
                $operation['security'] = [];
            }

            $paths[$actionPath][$httpMethod] = $operation;
        }

        return $paths;
    }

    private function buildEntityPaths(array $entity): array
    {
        $basePath = "{$this->apiPrefix}/{$entity['path']}";
        $name = $entity['name'];
        $isPublic = $this->isPublicResource($entity['class']);

        $listPath = [
            'get' => [
                'summary' => "List {$entity['path']}",
                'tags' => [$name],
                'parameters' => [
                    ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 1]],
                    ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 15]],
                    ['name' => 'sort', 'in' => 'query', 'schema' => ['type' => 'string']],
                    ['name' => 'fields', 'in' => 'query', 'schema' => ['type' => 'string']],
                ],
                'responses' => [
                    '200' => [
                        'description' => "Paginated list of {$entity['path']}",
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'data' => ['type' => 'array', 'items' => ['$ref' => "#/components/schemas/{$name}"]],
                                        'meta' => ['$ref' => '#/components/schemas/PaginationMeta'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'post' => [
                'summary' => "Create {$name}",
                'tags' => [$name],
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => "#/components/schemas/{$name}Create"],
                        ],
                    ],
                ],
                'responses' => [
                    '201' => ['description' => "{$name} created"],
                    '422' => ['description' => 'Validation error'],
                ],
            ],
        ];

        $itemPath = [
            'get' => [
                'summary' => "Get {$name} by ID",
                'tags' => [$name],
                'parameters' => [
                    ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                ],
                'responses' => [
                    '200' => [
                        'description' => "{$name} found",
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'data' => ['$ref' => "#/components/schemas/{$name}"],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '404' => ['description' => 'Not found'],
                ],
            ],
            'put' => [
                'summary' => "Replace {$name}",
                'tags' => [$name],
                'parameters' => [
                    ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                ],
                'requestBody' => [
                    'required' => true,
                    'content' => ['application/json' => ['schema' => ['$ref' => "#/components/schemas/{$name}Create"]]],
                ],
                'responses' => ['200' => ['description' => "{$name} updated"]],
            ],
            'patch' => [
                'summary' => "Update {$name}",
                'tags' => [$name],
                'parameters' => [
                    ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                ],
                'requestBody' => [
                    'content' => ['application/json' => ['schema' => ['$ref' => "#/components/schemas/{$name}Create"]]],
                ],
                'responses' => ['200' => ['description' => "{$name} updated"]],
            ],
            'delete' => [
                'summary' => "Delete {$name}",
                'tags' => [$name],
                'parameters' => [
                    ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                ],
                'responses' => ['204' => ['description' => 'Deleted']],
            ],
        ];

        if ($isPublic) {
            $listPath['get']['security'] = [];
            $listPath['post']['security'] = [];
            foreach ($itemPath as &$op) {
                $op['security'] = [];
            }
        }

        return [
            $basePath => $listPath,
            "{$basePath}/{id}" => $itemPath,
        ];
    }

    private function buildCommonSchemas(): array
    {
        return [
            'PaginationMeta' => [
                'type' => 'object',
                'properties' => [
                    'current_page' => ['type' => 'integer'],
                    'per_page' => ['type' => 'integer'],
                    'total' => ['type' => 'integer'],
                    'total_pages' => ['type' => 'integer'],
                ],
            ],
        ];
    }

    private function isPublicResource(string $class): bool
    {
        $ref = new ReflectionClass($class);

        return !empty($ref->getAttributes(PublicResource::class));
    }
}
