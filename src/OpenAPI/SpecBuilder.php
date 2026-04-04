<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\OpenAPI;

use Coagus\PhpApiBuilder\Attributes\PublicResource;
use Coagus\PhpApiBuilder\Helpers\Utils;
use ReflectionClass;

class SpecBuilder
{
    private string $title;
    private string $version;
    private string $apiPrefix;
    private array $entityClasses = [];

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
        // Convert snake to kebab for URL
        $path = str_replace('_', '-', $path);

        $this->entityClasses[] = [
            'class' => $entityClass,
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

        return $spec;
    }

    public function toJson(): string
    {
        return json_encode($this->build(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function buildEntityPaths(array $entity): array
    {
        $basePath = "{$this->apiPrefix}/{$entity['path']}";
        $name = $entity['name'];
        $isPublic = $this->isPublicResource($entity['class']);

        $listPath = [
            'get' => [
                'summary' => "List {$entity['path']}",
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
                'parameters' => [
                    ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                ],
                'responses' => ['204' => ['description' => 'Deleted']],
            ],
        ];

        if ($isPublic) {
            unset($listPath['get']['security'], $listPath['post']['security']);
            unset($itemPath['get']['security']);
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
