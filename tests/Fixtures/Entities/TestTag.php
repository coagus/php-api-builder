<?php

declare(strict_types=1);

namespace Tests\Fixtures\Entities;

use Coagus\PhpApiBuilder\Attributes\PrimaryKey;
use Coagus\PhpApiBuilder\Attributes\Table;
use Coagus\PhpApiBuilder\ORM\Entity;
use Coagus\PhpApiBuilder\Validation\Attributes\Required;

#[Table('tags')]
class TestTag extends Entity
{
    #[PrimaryKey]
    public int $id;

    #[Required]
    public string $name;
}
