<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\ORM\Drivers;

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
}
