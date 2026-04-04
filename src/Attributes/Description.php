<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
class Description
{
    public function __construct(
        public readonly string $text
    ) {}
}
