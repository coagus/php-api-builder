<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\ORM;

class QueryBuilder
{
    public function __construct(
        private readonly string $entityClass
    ) {}
}
