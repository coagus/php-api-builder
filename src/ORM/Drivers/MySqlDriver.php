<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\ORM\Drivers;

use PDO;

class MySqlDriver implements DriverInterface
{
    private const DEFAULT_CHARSET = 'utf8mb4';
    private const DEFAULT_COLLATION = 'utf8mb4_unicode_ci';

    public function getDsn(array $config): string
    {
        $host = $config['host'] ?? 'localhost';
        $port = $config['port'] ?? 3306;
        $database = $config['database'];
        $charset = $config['charset'] ?? self::DEFAULT_CHARSET;

        return "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
    }

    public function getAutoIncrementSyntax(): string
    {
        return 'AUTO_INCREMENT';
    }

    public function getBooleanType(): string
    {
        return 'TINYINT(1)';
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
        $updates = implode(', ', array_map(fn(string $f) => "{$f} = VALUES({$f})", $fields));

        return "ON DUPLICATE KEY UPDATE {$updates}";
    }

    public function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
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
        $charset = self::DEFAULT_CHARSET;
        $collation = self::DEFAULT_COLLATION;

        $pdo->exec("SET NAMES {$charset} COLLATE {$collation}");
        $pdo->exec("SET time_zone = '+00:00'");
    }

    public function getRefreshTokenTableDdl(): string
    {
        return <<<'SQL'
            CREATE TABLE IF NOT EXISTS refresh_tokens (
                id VARCHAR(64) PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                token_hash VARCHAR(128) NOT NULL,
                family_id VARCHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                revoked TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_refresh_family (family_id),
                INDEX idx_refresh_user (user_id),
                INDEX idx_refresh_hash (token_hash)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL;
    }
}
