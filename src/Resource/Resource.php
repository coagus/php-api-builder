<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\Resource;

use Coagus\PhpApiBuilder\Helpers\ApiResponse;
use Coagus\PhpApiBuilder\Http\Request;
use Coagus\PhpApiBuilder\Http\Response;

abstract class Resource
{
    protected ?Request $request = null;
    protected ?string $resourceId = null;
    protected ?string $action = null;
    protected ?Response $response = null;

    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    public function setResourceId(?string $id): void
    {
        $this->resourceId = $id;
    }

    public function setAction(?string $action): void
    {
        $this->action = $action;
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }

    protected function success(mixed $data, int $code = 200): void
    {
        $this->response = ApiResponse::success($data, $code);
    }

    protected function created(mixed $data, ?string $location = null): void
    {
        $this->response = ApiResponse::created($data, $location);
    }

    protected function noContent(): void
    {
        $this->response = ApiResponse::noContent();
    }

    protected function error(string $title, int $code = 400, ?string $detail = null, ?array $errors = null): void
    {
        $this->response = ApiResponse::error($title, $code, $detail, $this->request?->getPath(), $errors);
    }

    protected function getInput(): ?object
    {
        return $this->request?->getInput();
    }

    protected function getQueryParams(): array
    {
        return $this->request?->getQueryParams() ?? [];
    }

    protected function getUploadedFile(string $name): ?\Coagus\PhpApiBuilder\Http\UploadedFile
    {
        if (!isset($_FILES[$name])) {
            return null;
        }

        return new \Coagus\PhpApiBuilder\Http\UploadedFile($_FILES[$name]);
    }
}
