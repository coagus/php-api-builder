<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\Validation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Min
{
    public function __construct(
        public readonly int|float $min
    ) {}
}
