<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\ORM\Drivers;

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
}
