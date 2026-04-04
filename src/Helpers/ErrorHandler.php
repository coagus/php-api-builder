<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\Helpers;

use Coagus\PhpApiBuilder\Http\Response;

class ErrorHandler
{
    public static function handle(\Throwable $e, ?string $requestId = null): Response
    {
        $status = match (true) {
            $e instanceof \InvalidArgumentException => 400,
            $e instanceof \RuntimeException && str_contains($e->getMessage(), 'not found') => 404,
            default => 500,
        };

        $body = [
            'type' => 'about:blank',
            'title' => self::titleForStatus($status),
            'status' => $status,
            'detail' => $status === 500 ? 'An internal error occurred.' : $e->getMessage(),
        ];

        if ($requestId !== null) {
            $body['requestId'] = $requestId;
        }

        return new Response($body, $status);
    }

    public static function notFound(string $detail, ?string $instance = null): Response
    {
        return ApiResponse::error('Not Found', 404, $detail, $instance);
    }

    public static function methodNotAllowed(string $method, ?string $instance = null): Response
    {
        return ApiResponse::error('Method Not Allowed', 405, "The method '{$method}' is not supported for this resource.", $instance);
    }

    public static function validationError(array $errors, ?string $instance = null): Response
    {
        return ApiResponse::error('Validation Error', 422, 'One or more fields failed validation.', $instance, $errors);
    }

    private static function titleForStatus(int $status): string
    {
        return match ($status) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            409 => 'Conflict',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            default => 'Error',
        };
    }
}
