<?php

declare(strict_types=1);

namespace App\Entities;

use Coagus\PhpApiBuilder\ORM\Entity;
use Coagus\PhpApiBuilder\ORM\QueryBuilder;
use Coagus\PhpApiBuilder\Attributes\{Table, PrimaryKey, HasMany};
use Coagus\PhpApiBuilder\Validation\Attributes\{Required, Email, Unique, MaxLength, MinLength, Hidden};

#[Table('users')]
class User extends Entity
{
    #[PrimaryKey]
    public private(set) int $id;

    #[Required, MaxLength(100)]
    public string $name {
        set => trim($value);
    }

    #[Required, Email, Unique]
    public string $email {
        set => strtolower(trim($value));
    }

    #[Required, MinLength(6), Hidden]
    public string $password;

    public bool $active = true;

    public string $createdAt;

    #[HasMany(Post::class)]
    public array $posts;

    protected function beforeCreate(): void
    {
        $this->createdAt = date('Y-m-d H:i:s');
    }

    public static function scopeActive(QueryBuilder $query): QueryBuilder
    {
        return $query->where('active', true);
    }
}
