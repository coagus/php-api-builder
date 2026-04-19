<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\ORM\Drivers;

use PDO;

interface DriverInterface
{
    public function getDsn(array $config): string;

    public function getAutoIncrementSyntax(): string;

    public function getBooleanType(): string;

    public function getTimestampDefault(): string;

    public function getLimitOffsetSyntax(int $limit, int $offset): string;

    public function getUpsertSyntax(string $table, array $fields, string $conflictKey): string;

    public function quoteIdentifier(string $identifier): string;

    public function supportsReturning(): bool;

    /**
     * Returns the SQL expression that yields the driver-native "current timestamp".
     *
     * Used for soft-delete, audit columns, and any UPDATE/INSERT that needs
     * "now()" on the database side instead of binding a PHP-generated string.
     */
    public function getCurrentTimestampExpression(): string;

    /**
     * Applies driver-specific session settings right after the PDO connection opens.
     *
     * Examples: SQLite foreign-keys PRAGMA, Postgres SET TIME ZONE, MySQL SET NAMES.
     * Implementations MUST NOT assume the connection is idle — they should issue
     * at most a short sequence of SET/PRAGMA statements.
     */
    public function applySessionSettings(PDO $pdo): void;

    /**
     * Returns the driver-specific DDL used to create the refresh token table.
     *
     * The library ships a single schema; each driver expresses it with its own
     * native types (VARCHAR/INTEGER/DATETIME on MySQL, TEXT/INTEGER on SQLite, etc).
     */
    public function getRefreshTokenTableDdl(): string;
}
