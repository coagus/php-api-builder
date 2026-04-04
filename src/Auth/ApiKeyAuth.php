<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\Auth;

use Coagus\PhpApiBuilder\Http\Request;

class ApiKeyAuth
{
    private static ?string $headerName = null;
    private static ?\Closure $validator = null;

    public static function configure(string $headerName = 'X-API-Key', ?\Closure $validator = null): void
    {
        self::$headerName = $headerName;
        self::$validator = $validator;
    }

    public static function reset(): void
    {
        self::$headerName = null;
        self::$validator = null;
    }

    public static function validate(Request $request): bool
    {
        if (self::$headerName === null) {
            return false;
        }

        $apiKey = $request->getHeader(self::$headerName);
        if ($apiKey === null || $apiKey === '') {
            return false;
        }

        if (self::$validator !== null) {
            return (self::$validator)($apiKey);
        }

        // Default: check against env variable
        $expectedKey = $_ENV['API_KEY'] ?? null;

        return $expectedKey !== null && hash_equals($expectedKey, $apiKey);
    }

    public static function isConfigured(): bool
    {
        return self::$headerName !== null;
    }
}
