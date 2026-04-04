<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\Helpers;

class SensitiveDataFilter
{
    private const PROTECTED = '***PROTECTED***';

    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'token',
        'access_token',
        'refresh_token',
        'api_key',
        'apikey',
        'secret',
        'authorization',
        'credit_card',
        'card_number',
        'cvv',
        'ssn',
    ];

    public static function filter(mixed $data): mixed
    {
        if (is_array($data)) {
            return self::filterArray($data);
        }

        if (is_object($data)) {
            return self::filterArray((array) $data);
        }

        return $data;
    }

    private static function filterArray(array $data): array
    {
        $filtered = [];

        foreach ($data as $key => $value) {
            $normalizedKey = strtolower(str_replace(['-', ' '], '_', (string) $key));

            if (in_array($normalizedKey, self::SENSITIVE_KEYS, true)) {
                $filtered[$key] = self::PROTECTED;
            } elseif (is_array($value)) {
                $filtered[$key] = self::filterArray($value);
            } elseif (is_object($value)) {
                $filtered[$key] = self::filterArray((array) $value);
            } else {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }
}
