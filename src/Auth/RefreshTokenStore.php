<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\Auth;

use Coagus\PhpApiBuilder\ORM\Connection;
use RuntimeException;

class RefreshTokenStore
{
    public static function createTable(): void
    {
        $connection = Connection::getInstance();
        $ddl = $connection->getDriver()->getRefreshTokenTableDdl();
        $connection->exec($ddl);
    }

    public static function store(string $tokenId, int $userId, string $tokenHash, string $familyId, int $expiresAt): void
    {
        Connection::getInstance()->execute(
            "INSERT INTO refresh_tokens (id, user_id, token_hash, family_id, expires_at) VALUES (?, ?, ?, ?, ?)",
            [$tokenId, $userId, $tokenHash, $familyId, date('Y-m-d H:i:s', $expiresAt)]
        );
    }

    public static function findByHash(string $tokenHash): ?array
    {
        $rows = Connection::getInstance()->query(
            "SELECT * FROM refresh_tokens WHERE token_hash = ? LIMIT 1",
            [$tokenHash]
        );

        return $rows[0] ?? null;
    }

    public static function revoke(string $tokenId): void
    {
        Connection::getInstance()->execute(
            "UPDATE refresh_tokens SET revoked = 1 WHERE id = ?",
            [$tokenId]
        );
    }

    public static function revokeFamily(string $familyId): void
    {
        Connection::getInstance()->execute(
            "UPDATE refresh_tokens SET revoked = 1 WHERE family_id = ?",
            [$familyId]
        );
    }

    public static function revokeAllForUser(int $userId): void
    {
        Connection::getInstance()->execute(
            "UPDATE refresh_tokens SET revoked = 1 WHERE user_id = ?",
            [$userId]
        );
    }

    public static function rotateToken(string $refreshToken): array
    {
        $tokenHash = hash('sha256', $refreshToken);
        $record = self::findByHash($tokenHash);

        if ($record === null) {
            throw new RuntimeException('Refresh token not found.');
        }

        if ((int) $record['revoked'] === 1) {
            // Reuse detected — revoke entire family
            self::revokeFamily($record['family_id']);
            throw new RuntimeException('Refresh token reuse detected. All tokens in family revoked.');
        }

        if (strtotime($record['expires_at']) < time()) {
            throw new RuntimeException('Refresh token expired.');
        }

        // Revoke the used token
        self::revoke($record['id']);

        $userId = (int) $record['user_id'];
        $familyId = $record['family_id'];

        // Generate new tokens
        $newRefreshToken = Auth::generateRefreshToken($userId, $familyId);
        $decoded = Auth::decodeToken($newRefreshToken);
        $newTokenHash = hash('sha256', $newRefreshToken);

        self::store(
            $decoded->jti,
            $userId,
            $newTokenHash,
            $familyId,
            $decoded->exp
        );

        return [
            'refreshToken' => $newRefreshToken,
            'userId' => $userId,
            'familyId' => $familyId,
        ];
    }
}
