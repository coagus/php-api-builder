<?php

declare(strict_types=1);

namespace DemoApi\Entities;

use Coagus\PhpApiBuilder\Attributes\BelongsTo;
use Coagus\PhpApiBuilder\Attributes\HasMany;
use Coagus\PhpApiBuilder\Attributes\PrimaryKey;
use Coagus\PhpApiBuilder\Attributes\SoftDelete;
use Coagus\PhpApiBuilder\Attributes\Table;
use Coagus\PhpApiBuilder\Attributes\Description;
use Coagus\PhpApiBuilder\Attributes\Example;
use Coagus\PhpApiBuilder\ORM\Entity;
use Coagus\PhpApiBuilder\Validation\Attributes\Email;
use Coagus\PhpApiBuilder\Validation\Attributes\Hidden;
use Coagus\PhpApiBuilder\Validation\Attributes\MaxLength;
use Coagus\PhpApiBuilder\Validation\Attributes\MinLength;
use Coagus\PhpApiBuilder\Validation\Attributes\Required;

#[Table('users')]
#[SoftDelete]
#[Description('Application users')]
class User extends Entity
{
    #[PrimaryKey]
    public int $id;

    #[Required, MinLength(2), MaxLength(100)]
    #[Description('Full name of the user')]
    #[Example('Carlos García')]
    public string $name;

    #[Required, Email, MaxLength(150)]
    #[Description('Email address (unique)')]
    #[Example('carlos@example.com')]
    public string $email;

    #[Required, Hidden]
    public string $password;

    public bool $active = false;

    public int $roleId;

    public ?string $deletedAt = null;

    #[BelongsTo(Role::class, 'role_id')]
    public ?Role $role = null;

    #[HasMany(Order::class, 'user_id')]
    public array $orders = [];

    protected function beforeCreate(): void
    {
        $this->active = false;
    }
}
