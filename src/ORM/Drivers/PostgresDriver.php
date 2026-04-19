<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\ORM\Drivers;

use PDO;

class PostgresDriver implements DriverInterface
{
    public function getDsn(array $config): string
    {
        $host = $config['host'] ?? 'localhost';
        $port = $config['port'] ?? 5432;
        $database = $config['database'];

        return "pgsql:host={$host};port={$port};dbname={$database}";
    }

    public function getAutoIncrementSyntax(): string
    {
        return 'SERIAL';
    }

    public function getBooleanType(): string
    {
        return 'BOOLEAN';
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
        return true;
    }

    public function getCurrentTimestampExpression(): string
    {
        return 'CURRENT_TIMESTAMP';
    }

    public function applySessionSettings(PDO $pdo): void
    {
        $pdo->exec("SET TIME ZONE 'UTC'");
        $pdo->exec("SET client_encoding = 'UTF8'");
    }

    public function getRefreshTokenTableDdl(): string
    {
        return <<<'SQL'
            CREATE TABLE IF NOT EXISTS refresh_tokens (
                id VARCHAR(64) PRIMARY KEY,
                user_id INTEGER NOT NULL,
                token_hash VARCHAR(128) NOT NULL,
                family_id VARCHAR(64) NOT NULL,
                expires_at TIMESTAMPTZ NOT NULL,
                revoked SMALLINT NOT NULL DEFAULT 0,
                created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
            SQL;
    }
}
