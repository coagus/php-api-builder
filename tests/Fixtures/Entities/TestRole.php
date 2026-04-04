<?php

declare(strict_types=1);

namespace Tests\Fixtures\Entities;

use Coagus\PhpApiBuilder\Attributes\PrimaryKey;
use Coagus\PhpApiBuilder\Attributes\Table;
use Coagus\PhpApiBuilder\ORM\Entity;
use Coagus\PhpApiBuilder\Validation\Attributes\Required;
use Coagus\PhpApiBuilder\Validation\Attributes\MaxLength;

#[Table('roles')]
class TestRole extends Entity
{
    #[PrimaryKey]
    public int $id;

    #[Required, MaxLength(50)]
    public string $name;
}
