<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\Auth;

class ScopeValidator
{
    public static function hasScope(object $tokenPayload, string $requiredScope): bool
    {
        $scopes = $tokenPayload->scopes ?? [];

        if (in_array('*', $scopes, true)) {
            return true;
        }

        if (in_array($requiredScope, $scopes, true)) {
            return true;
        }

        // Check wildcard: "users:*" matches "users:read"
        $parts = explode(':', $requiredScope);
        if (count($parts) === 2) {
            $wildcard = $parts[0] . ':*';
            if (in_array($wildcard, $scopes, true)) {
                return true;
            }
        }

        return false;
    }

    public static function hasAllScopes(object $tokenPayload, array $requiredScopes): bool
    {
        foreach ($requiredScopes as $scope) {
            if (!self::hasScope($tokenPayload, $scope)) {
                return false;
            }
        }

        return true;
    }

    public static function hasAnyScope(object $tokenPayload, array $requiredScopes): bool
    {
        foreach ($requiredScopes as $scope) {
            if (self::hasScope($tokenPayload, $scope)) {
                return true;
            }
        }

        return false;
    }
}
