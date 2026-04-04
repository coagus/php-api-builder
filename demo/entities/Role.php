<?php

declare(strict_types=1);

namespace DemoApi\Entities;

use Coagus\PhpApiBuilder\Attributes\PrimaryKey;
use Coagus\PhpApiBuilder\Attributes\Table;
use Coagus\PhpApiBuilder\Attributes\Description;
use Coagus\PhpApiBuilder\ORM\Entity;
use Coagus\PhpApiBuilder\Validation\Attributes\MaxLength;
use Coagus\PhpApiBuilder\Validation\Attributes\Required;

#[Table('roles')]
#[Description('User roles for access control')]
class Role extends Entity
{
    #[PrimaryKey]
    public int $id;

    #[Required, MaxLength(50)]
    #[Description('Role name')]
    public string $name;
}
