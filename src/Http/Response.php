<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\Http;

class Response
{
    private int $statusCode;
    private array $headers = [];
    private mixed $body;
    private bool $sent = false;

    public function __construct(mixed $body = null, int $statusCode = 200)
    {
        $this->body = $body;
        $this->statusCode = $statusCode;
        $this->headers['Content-Type'] = 'application/json; charset=utf-8';
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setStatusCode(int $code): static
    {
        $this->statusCode = $code;

        return $this;
    }

    public function header(string $name, string $value): static
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getBody(): mixed
    {
        return $this->body;
    }

    public function setBody(mixed $body): static
    {
        $this->body = $body;

        return $this;
    }

    public function toJson(): string
    {
        if ($this->body === null) {
            return '';
        }

        return json_encode($this->body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function send(): void
    {
        if ($this->sent) {
            return;
        }

        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            if ($value === '') {
                header_remove($name);
            } else {
                header("{$name}: {$value}");
            }
        }

        $json = $this->toJson();
        if ($json !== '') {
            echo $json;
        }

        $this->sent = true;
    }

    public static function json(mixed $data, int $statusCode = 200): static
    {
        return new static($data, $statusCode);
    }
}
