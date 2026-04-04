<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RuntimeException;

class Auth
{
    private static ?array $config = null;

    public static function configure(array $config): void
    {
        self::$config = array_merge([
            'algorithm' => 'HS256',
            'secret' => null,
            'private_key' => null,
            'public_key' => null,
            'access_ttl' => 900,
            'refresh_ttl' => 604800,
            'issuer' => 'php-api-builder',
            'audience' => 'php-api-builder',
        ], $config);
    }

    public static function reset(): void
    {
        self::$config = null;
    }

    public static function generateAccessToken(array $userData, array $scopes = [], ?int $expiresIn = null): string
    {
        $config = self::getConfig();
        $now = time();
        $ttl = $expiresIn ?? $config['access_ttl'];

        $payload = [
            'iss' => $config['issuer'],
            'sub' => 'user:' . ($userData['id'] ?? 0),
            'aud' => $config['audience'],
            'iat' => $now,
            'exp' => $now + $ttl,
            'jti' => bin2hex(random_bytes(16)),
            'scopes' => $scopes,
            'data' => $userData,
        ];

        return JWT::encode($payload, self::getSigningKey(), $config['algorithm']);
    }

    public static function generateRefreshToken(int $userId, ?string $familyId = null): string
    {
        $config = self::getConfig();
        $now = time();

        $payload = [
            'iss' => $config['issuer'],
            'sub' => 'user:' . $userId,
            'iat' => $now,
            'exp' => $now + $config['refresh_ttl'],
            'jti' => bin2hex(random_bytes(16)),
            'type' => 'refresh',
            'family_id' => $familyId ?? bin2hex(random_bytes(16)),
        ];

        return JWT::encode($payload, self::getSigningKey(), $config['algorithm']);
    }

    public static function validateToken(string $token): object
    {
        $config = self::getConfig();

        try {
            $decoded = JWT::decode($token, new Key(self::getVerificationKey(), $config['algorithm']));
        } catch (\Exception $e) {
            throw new RuntimeException('Invalid token: ' . $e->getMessage());
        }

        if (isset($decoded->iss) && $decoded->iss !== $config['issuer']) {
            throw new RuntimeException('Invalid token issuer.');
        }

        if (isset($decoded->aud) && $decoded->aud !== $config['audience']) {
            throw new RuntimeException('Invalid token audience.');
        }

        return $decoded;
    }

    public static function decodeToken(string $token): object
    {
        return self::validateToken($token);
    }

    public static function getConfig(): array
    {
        if (self::$config === null) {
            throw new RuntimeException('Auth not configured. Call Auth::configure() first.');
        }

        return self::$config;
    }

    private static function getSigningKey(): string
    {
        $config = self::getConfig();

        if (in_array($config['algorithm'], ['RS256', 'RS384', 'RS512', 'ES256', 'ES384', 'ES512'])) {
            if ($config['private_key'] === null) {
                throw new RuntimeException('Private key required for asymmetric algorithm.');
            }
            $key = $config['private_key'];
            if (file_exists($key)) {
                return file_get_contents($key);
            }
            return $key;
        }

        if ($config['secret'] === null) {
            throw new RuntimeException('Secret key required for symmetric algorithm.');
        }

        return $config['secret'];
    }

    private static function getVerificationKey(): string
    {
        $config = self::getConfig();

        if (in_array($config['algorithm'], ['RS256', 'RS384', 'RS512', 'ES256', 'ES384', 'ES512'])) {
            if ($config['public_key'] === null) {
                throw new RuntimeException('Public key required for asymmetric algorithm.');
            }
            $key = $config['public_key'];
            if (file_exists($key)) {
                return file_get_contents($key);
            }
            return $key;
        }

        if ($config['secret'] === null) {
            throw new RuntimeException('Secret key required for symmetric algorithm.');
        }

        return $config['secret'];
    }
}
