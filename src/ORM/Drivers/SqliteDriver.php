<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\ORM\Drivers;

use PDO;

class SqliteDriver implements DriverInterface
{
    private const BUSY_TIMEOUT_MS = 5000;

    public function getDsn(array $config): string
    {
        $database = $config['database'] ?? ':memory:';

        return "sqlite:{$database}";
    }

    public function getAutoIncrementSyntax(): string
    {
        return 'AUTOINCREMENT';
    }

    public function getBooleanType(): string
    {
        return 'INTEGER';
    }

    public function getTimestampDefault(): string
    {
        return 'CURRENT_TIMESTAMP';
    }

    public function getLimitOffsetSyntax(int $limit, int $offset): string
    {
        return "LIMIT {$limit} OFFSET {$offset}";
    }

    public function getUpsertSyntax(string $table, array $fields, string $conflictKey): string
    {
        $updates = implode(', ', array_map(fn(string $f) => "{$f} = EXCLUDED.{$f}", $fields));

        return "ON CONFLICT ({$conflictKey}) DO UPDATE SET {$updates}";
    }

    public function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    public function supportsReturning(): bool
    {
        return false;
    }

    public function getCurrentTimestampExpression(): string
    {
        return 'CURRENT_TIMESTAMP';
    }

    public function applySessionSettings(PDO $pdo): void
    {
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = ' . self::BUSY_TIMEOUT_MS);

        // WAL is not meaningful for :memory:, and can fail on read-only filesystems.
        // Best-effort; swallow the error.
        try {
            $pdo->exec('PRAGMA journal_mode = WAL');
        } catch (\PDOException) {
            // Fall back to default journal mode silently.
        }
    }

    public function getRefreshTokenTableDdl(): string
    {
        return <<<'SQL'
            CREATE TABLE IF NOT EXISTS refresh_tokens (
                id TEXT PRIMARY KEY,
                user_id INTEGER NOT NULL,
                token_hash TEXT NOT NULL,
                family_id TEXT NOT NULL,
                expires_at TEXT NOT NULL,
                revoked INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
            SQL;
    }
}
