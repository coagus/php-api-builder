<?php

declare(strict_types=1);

namespace Tests\Fixtures\Entities;

use Coagus\PhpApiBuilder\Attributes\BelongsTo;
use Coagus\PhpApiBuilder\Attributes\BelongsToMany;
use Coagus\PhpApiBuilder\Attributes\HasMany;
use Coagus\PhpApiBuilder\Attributes\PrimaryKey;
use Coagus\PhpApiBuilder\Attributes\SoftDelete;
use Coagus\PhpApiBuilder\Attributes\Table;
use Coagus\PhpApiBuilder\ORM\Entity;
use Coagus\PhpApiBuilder\Validation\Attributes\Email;
use Coagus\PhpApiBuilder\Validation\Attributes\Hidden;
use Coagus\PhpApiBuilder\Validation\Attributes\MaxLength;
use Coagus\PhpApiBuilder\Validation\Attributes\Required;

#[Table('users')]
#[SoftDelete]
class TestUser extends Entity
{
    #[PrimaryKey]
    public int $id;

    #[Required, MaxLength(100)]
    public string $name;

    #[Required, Email]
    public string $email;

    #[Hidden]
    public string $password;

    public bool $active = false;

    public int $roleId;

    public ?string $deletedAt = null;

    #[BelongsTo(TestRole::class, 'role_id')]
    public ?TestRole $role = null;

    #[HasMany(TestOrder::class, 'user_id')]
    public array $orders = [];

    #[BelongsToMany(TestTag::class, 'user_tags', 'user_id', 'tag_id')]
    public array $tags = [];

    private bool $hookCalled = false;

    protected function beforeCreate(): void
    {
        $this->hookCalled = true;
    }

    protected function afterCreate(): void
    {
        $this->hookCalled = true;
    }

    public function wasHookCalled(): bool
    {
        return $this->hookCalled;
    }

    public function resetHookFlag(): void
    {
        $this->hookCalled = false;
    }
}
