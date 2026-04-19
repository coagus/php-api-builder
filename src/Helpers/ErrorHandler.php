<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\Helpers;

use Coagus\PhpApiBuilder\Exceptions\EntityNotFoundException;
use Coagus\PhpApiBuilder\Exceptions\ValidationException;
use Coagus\PhpApiBuilder\Http\Response;

class ErrorHandler
{
    private const GENERIC_INTERNAL_DETAIL = 'An internal error occurred.';

    public static function handle(\Throwable $e, ?string $requestId = null): Response
    {
        $mapping = self::mapException($e);
        $status = $mapping['status'];
        $title = self::titleForStatus($status);

        $body = [
            'type' => 'about:blank',
            'title' => $title,
            'status' => $status,
        ];

        $detail = self::resolveDetail($e, $mapping);
        if ($detail !== null) {
            $body['detail'] = $detail;
        }

        if ($e instanceof ValidationException) {
            $body['errors'] = $e->errors;
        }

        if ($requestId !== null) {
            $body['requestId'] = $requestId;
        }

        $response = new Response($body, $status);
        $response->header('Content-Type', ApiResponse::PROBLEM_JSON_CONTENT_TYPE);

        return $response;
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

    /**
     * Map exception class → HTTP status + "safe to expose" flag.
     * Matching by class avoids brittle str_contains() on exception messages.
     *
     * @return array{status: int, safeMessage: bool}
     */
    private static function mapException(\Throwable $e): array
    {
        return match (true) {
            $e instanceof ValidationException => ['status' => 422, 'safeMessage' => true],
            $e instanceof EntityNotFoundException => ['status' => 404, 'safeMessage' => true],
            $e instanceof \InvalidArgumentException => ['status' => 400, 'safeMessage' => true],
            default => ['status' => 500, 'safeMessage' => false],
        };
    }

    /**
     * @param array{status: int, safeMessage: bool} $mapping
     */
    private static function resolveDetail(\Throwable $e, array $mapping): ?string
    {
        if (!$mapping['safeMessage']) {
            // Never leak internal error messages in production.
            return self::isDebug() ? $e->getMessage() : self::GENERIC_INTERNAL_DETAIL;
        }

        $message = $e->getMessage();

        return $message !== '' ? $message : null;
    }

    private static function isDebug(): bool
    {
        $value = $_ENV['APP_DEBUG'] ?? 'false';

        return $value === 'true' || $value === '1';
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
            422 => 'Validation Error',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            default => 'Error',
        };
    }
}
