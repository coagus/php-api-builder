<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class BelongsToMany
{
    public function __construct(
        public readonly string $entity,
        public readonly ?string $pivotTable = null,
        public readonly ?string $foreignPivotKey = null,
        public readonly ?string $relatedPivotKey = null
    ) {}
}
