<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\Helpers;

class RequestContext
{
    private static ?self $instance = null;

    private ?string $requestId = null;
    private ?int $userId = null;
    private ?string $method = null;
    private ?string $uri = null;
    private ?string $entity = null;
    private ?string $operation = null;
    private ?array $input = null;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    public function setRequestId(string $id): void
    {
        $this->requestId = $id;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    public function setUserId(?int $id): void
    {
        $this->userId = $id;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setMethod(string $method): void
    {
        $this->method = $method;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function setUri(string $uri): void
    {
        $this->uri = $uri;
    }

    public function getUri(): ?string
    {
        return $this->uri;
    }

    public function setEntity(?string $entity): void
    {
        $this->entity = $entity;
    }

    public function getEntity(): ?string
    {
        return $this->entity;
    }

    public function setOperation(?string $operation): void
    {
        $this->operation = $operation;
    }

    public function getOperation(): ?string
    {
        return $this->operation;
    }

    public function setInput(?array $input): void
    {
        $this->input = $input;
    }

    public function getInput(): ?array
    {
        return $this->input;
    }

    public function toArray(): array
    {
        return array_filter([
            'request_id' => $this->requestId,
            'user_id' => $this->userId,
            'method' => $this->method,
            'uri' => $this->uri,
            'entity' => $this->entity,
            'operation' => $this->operation,
        ], fn($v) => $v !== null);
    }
}
