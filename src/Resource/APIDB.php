<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\Resource;

use Coagus\PhpApiBuilder\Helpers\Utils;
use Coagus\PhpApiBuilder\ORM\Entity;
use Coagus\PhpApiBuilder\ORM\QueryBuilder;
use RuntimeException;

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
        } catch (RuntimeException $e) {
            $errors = json_decode($e->getMessage(), true);
            if (is_array($errors)) {
                $this->error('Validation Error', 422, 'One or more fields failed validation.', $errors);
                return;
            }
            throw $e;
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
        } catch (RuntimeException $e) {
            $errors = json_decode($e->getMessage(), true);
            if (is_array($errors)) {
                $this->error('Validation Error', 422, 'One or more fields failed validation.', $errors);
                return;
            }
            throw $e;
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
        } catch (RuntimeException $e) {
            $errors = json_decode($e->getMessage(), true);
            if (is_array($errors)) {
                $this->error('Validation Error', 422, 'One or more fields failed validation.', $errors);
                return;
            }
            throw $e;
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

        $fields = explode(',', $sort);
        foreach ($fields as $field) {
            $field = trim($field);
            if (str_starts_with($field, '-')) {
                $query->orderBy(Utils::snakeToCamel(substr($field, 1)), 'desc');
            } else {
                $query->orderBy(Utils::snakeToCamel($field), 'asc');
            }
        }
    }

    private function applyFields(QueryBuilder $query, array $params): void
    {
        $fields = $params['fields'] ?? null;
        if ($fields === null) {
            return;
        }

        $fieldList = array_map('trim', explode(',', $fields));
        $query->select(...$fieldList);
    }

    private function successWithMeta(mixed $data, int $code, array $meta): void
    {
        $this->response = \Coagus\PhpApiBuilder\Helpers\ApiResponse::paginated(
            is_array($data) ? $data : [$data],
            $meta
        );
    }
}
