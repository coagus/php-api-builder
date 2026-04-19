<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\Resource;

use Coagus\PhpApiBuilder\Exceptions\ValidationException;
use Coagus\PhpApiBuilder\Helpers\Utils;
use Coagus\PhpApiBuilder\ORM\Entity;
use Coagus\PhpApiBuilder\ORM\QueryBuilder;

abstract class APIDB extends Resource
{
    protected string $entity;

    public function get(): void
    {
        if ($this->resourceId !== null) {
            $this->getOne();
        } else {
            $this->getList();
        }
    }

    public function post(): void
    {
        $entity = $this->getFilledEntity();

        try {
            $entity->save();
        } catch (ValidationException $e) {
            $this->error('Validation Error', 422, 'One or more fields failed validation.', $e->errors);
            return;
        }

        $this->created($entity);
    }

    public function put(): void
    {
        if ($this->resourceId === null) {
            $this->error('Resource ID required', 400);
            return;
        }

        $entityClass = $this->entity;
        $existing = $entityClass::find((int) $this->resourceId);

        if ($existing === null) {
            $this->error('Resource not found', 404);
            return;
        }

        $input = $this->request->getRawBody();
        $existing->fill($input);

        try {
            $existing->save();
        } catch (ValidationException $e) {
            $this->error('Validation Error', 422, 'One or more fields failed validation.', $e->errors);
            return;
        }

        $this->success($existing);
    }

    public function patch(): void
    {
        if ($this->resourceId === null) {
            $this->error('Resource ID required', 400);
            return;
        }

        $entityClass = $this->entity;
        $existing = $entityClass::find((int) $this->resourceId);

        if ($existing === null) {
            $this->error('Resource not found', 404);
            return;
        }

        $input = $this->request->getRawBody();
        $existing->fill($input);

        try {
            $existing->save();
        } catch (ValidationException $e) {
            $this->error('Validation Error', 422, 'One or more fields failed validation.', $e->errors);
            return;
        }

        $this->success($existing);
    }

    public function delete(): void
    {
        if ($this->resourceId === null) {
            $this->error('Resource ID required', 400);
            return;
        }

        $entityClass = $this->entity;
        $existing = $entityClass::find((int) $this->resourceId);

        if ($existing === null) {
            $this->error('Resource not found', 404);
            return;
        }

        $existing->delete();
        $this->noContent();
    }

    protected function getFilledEntity(): Entity
    {
        $entityClass = $this->entity;
        $entity = new $entityClass();
        $input = $this->request->getRawBody();
        $entity->fill($input);

        return $entity;
    }

    protected function getEmptyEntity(): Entity
    {
        $entityClass = $this->entity;

        return new $entityClass();
    }

    private function getOne(): void
    {
        $entityClass = $this->entity;
        $entity = $entityClass::find((int) $this->resourceId);

        if ($entity === null) {
            $this->error('Resource not found', 404);
            return;
        }

        $this->success($entity);
    }

    private function getList(): void
    {
        $entityClass = $this->entity;
        $query = $entityClass::query();
        $params = $this->getQueryParams();

        $this->applyFilters($query, $params);
        $this->applySorting($query, $params);
        $this->applyFields($query, $params);

        $page = (int) ($params['page'] ?? 1);
        $perPage = (int) ($params['per_page'] ?? 15);

        $result = $query->paginate($page, $perPage);

        $this->successWithMeta($result['data'], 200, $result['meta']);
    }

    private function applyFilters(QueryBuilder $query, array $params): void
    {
        $filter = $params['filter'] ?? [];
        if (!is_array($filter)) {
            return;
        }

        foreach ($filter as $field => $value) {
            $camelField = Utils::snakeToCamel($field);
            if ($value === 'true') {
                $query->where($camelField, 1);
            } elseif ($value === 'false') {
                $query->where($camelField, 0);
            } elseif ($value === 'null') {
                $query->whereNull($camelField);
            } else {
                $query->where($camelField, $value);
            }
        }
    }

    private function applySorting(QueryBuilder $query, array $params): void
    {
        $sort = $params['sort'] ?? null;
        if ($sort === null) {
            return;
        }

        $allowlist = $query->getColumnAllowlist();

        foreach (explode(',', $sort) as $rawField) {
            $field = trim($rawField);
            if ($field === '') {
                continue;
            }

            $direction = 'asc';
            if (str_starts_with($field, '-')) {
                $direction = 'desc';
                $field = substr($field, 1);
            }

            $column = Utils::camelToSnake($field);
            if (!in_array($column, $allowlist, true)) {
                // Silently drop unknown sort fields — never interpolate into SQL.
                continue;
            }

            $query->orderBy(Utils::snakeToCamel($column), $direction);
        }
    }

    private function applyFields(QueryBuilder $query, array $params): void
    {
        $fields = $params['fields'] ?? null;
        if ($fields === null) {
            return;
        }

        $allowlist = $query->getColumnAllowlist();
        $requested = array_map('trim', explode(',', $fields));
        $safe = [];

        foreach ($requested as $field) {
            if ($field === '') {
                continue;
            }
            $column = Utils::camelToSnake($field);
            if (in_array($column, $allowlist, true)) {
                $safe[] = $field;
            }
        }

        if (!empty($safe)) {
            $query->select(...$safe);
        }
    }

    private function successWithMeta(mixed $data, int $code, array $meta): void
    {
        $this->response = \Coagus\PhpApiBuilder\Helpers\ApiResponse::paginated(
            is_array($data) ? $data : [$data],
            $meta
        );
    }
}
