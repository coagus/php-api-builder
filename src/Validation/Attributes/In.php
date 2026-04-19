<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\Validation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class In
{
    public readonly array $values;

    /**
     * Accepts both spread form — `#[In('a', 'b')]` — and array form — `#[In(['a', 'b'])]`.
     * When a single array argument is received, it is adopted as the allowed list verbatim
     * (otherwise the variadic would wrap it into `[[...]]`, breaking `in_array` and `implode`).
     */
    public function __construct(mixed ...$values)
    {
        $this->values = (count($values) === 1 && is_array($values[0])) ? $values[0] : $values;
    }
}
