<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\ORM\Drivers;

class MySqlDriver implements DriverInterface
{
    public function getDsn(array $config): string
    {
        $host = $config['host'] ?? 'localhost';
        $port = $config['port'] ?? 3306;
        $database = $config['database'];
        $charset = $config['charset'] ?? 'utf8mb4';

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
}
