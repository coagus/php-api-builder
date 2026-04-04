<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\Http;

class Request
{
    private string $method;
    private string $uri;
    private string $path;
    private array $queryParams;
    private array $headers;
    private ?object $body;
    private array $rawBody;

    public function __construct(
        ?string $method = null,
        ?string $uri = null,
        ?array $headers = null,
        ?string $rawBody = null
    ) {
        $this->method = strtoupper($method ?? $_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->uri = $uri ?? $_SERVER['REQUEST_URI'] ?? '/';
        $this->headers = $headers ?? $this->parseHeaders();

        $parsedUrl = parse_url($this->uri);
        $this->path = $parsedUrl['path'] ?? '/';

        if ($rawBody !== null) {
            $this->rawBody = json_decode($rawBody, true) ?? [];
        } else {
            $input = file_get_contents('php://input');
            $this->rawBody = $input ? (json_decode($input, true) ?? []) : [];
        }

        $this->body = !empty($this->rawBody) ? (object) $this->rawBody : null;

        $this->queryParams = [];
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $this->queryParams);
        } elseif (!empty($_GET)) {
            $this->queryParams = $_GET;
        }
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function getQueryParam(string $key, mixed $default = null): mixed
    {
        return $this->queryParams[$key] ?? $default;
    }

    public function getHeader(string $name): ?string
    {
        $normalized = strtolower($name);
        foreach ($this->headers as $key => $value) {
            if (strtolower($key) === $normalized) {
                return $value;
            }
        }

        return null;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getInput(): ?object
    {
        return $this->body;
    }

    public function getRawBody(): array
    {
        return $this->rawBody;
    }

    public function getBearerToken(): ?string
    {
        $auth = $this->getHeader('Authorization');
        if ($auth && str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }

        return null;
    }

    private function parseHeaders(): array
    {
        $headers = [];
        if (function_exists('getallheaders')) {
            $headers = getallheaders() ?: [];
        } else {
            foreach ($_SERVER as $key => $value) {
                if (str_starts_with($key, 'HTTP_')) {
                    $headerName = str_replace('_', '-', substr($key, 5));
                    $headers[$headerName] = $value;
                }
            }
        }

        return $headers;
    }
}
