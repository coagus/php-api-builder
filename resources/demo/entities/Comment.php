<?php

declare(strict_types=1);

namespace App\Entities;

use Coagus\PhpApiBuilder\ORM\Entity;
use Coagus\PhpApiBuilder\Attributes\{Table, PrimaryKey, BelongsTo};
use Coagus\PhpApiBuilder\Validation\Attributes\{Required, MaxLength};

#[Table('comments')]
class Comment extends Entity
{
    #[PrimaryKey]
    public private(set) int $id;

    #[Required, MaxLength(500)]
    public string $body;

    #[BelongsTo(Post::class)]
    public int $postId;

    #[BelongsTo(User::class)]
    public int $userId;

    public string $createdAt;

    protected function beforeCreate(): void
    {
        $this->createdAt = date('Y-m-d H:i:s');
    }
}
