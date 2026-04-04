<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Route
{
    public function __construct(
        public readonly string $path
    ) {}
}
