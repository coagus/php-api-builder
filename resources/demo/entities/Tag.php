<?php

declare(strict_types=1);

namespace App\Entities;

use Coagus\PhpApiBuilder\ORM\Entity;
use Coagus\PhpApiBuilder\Attributes\{Table, PrimaryKey, BelongsToMany};
use Coagus\PhpApiBuilder\Validation\Attributes\{Required, Unique, MaxLength};

#[Table('tags')]
class Tag extends Entity
{
    #[PrimaryKey]
    public private(set) int $id;

    #[Required, Unique, MaxLength(50)]
    public string $name;

    #[BelongsToMany(Post::class, pivotTable: 'post_tags', foreignPivotKey: 'tag_id', relatedPivotKey: 'post_id')]
    public array $posts;
}
