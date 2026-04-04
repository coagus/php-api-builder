<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class HasMany
{
    public function __construct(
        public readonly string $entity,
        public readonly ?string $foreignKey = null
    ) {}
}
