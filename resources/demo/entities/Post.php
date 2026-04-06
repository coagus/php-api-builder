<?php

declare(strict_types=1);

namespace App\Entities;

use Coagus\PhpApiBuilder\ORM\Entity;
use Coagus\PhpApiBuilder\Attributes\{Table, PrimaryKey, SoftDelete, BelongsTo, HasMany, BelongsToMany, Description, Example};
use Coagus\PhpApiBuilder\Validation\Attributes\{Required, MaxLength, DefaultValue, In, IsReadOnly};

#[Table('posts')]
#[SoftDelete]
class Post extends Entity
{
    #[PrimaryKey]
    public private(set) int $id;

    #[Required, MaxLength(200)]
    #[Description('The title of the blog post')]
    #[Example('Getting Started with PHP API Builder')]
    public string $title {
        set => trim($value);
    }

    #[IsReadOnly, MaxLength(200)]
    public string $slug;

    #[Required]
    #[Description('The full content of the post')]
    public string $body;

    #[DefaultValue('draft')]
    #[In(['draft', 'published', 'archived'])]
    #[Description('Current publication status')]
    public string $status;

    #[BelongsTo(User::class)]
    public int $userId;

    #[IsReadOnly]
    public string $createdAt;

    #[IsReadOnly]
    public string $updatedAt;

    #[HasMany(Comment::class)]
    public array $comments;

    #[BelongsToMany(Tag::class, pivotTable: 'post_tags', foreignPivotKey: 'post_id', relatedPivotKey: 'tag_id')]
    public array $tags;

    protected function beforeCreate(): void
    {
        $this->slug = $this->generateSlug($this->title);
        $this->status = $this->status ?? 'draft';
        $this->createdAt = date('Y-m-d H:i:s');
        $this->updatedAt = date('Y-m-d H:i:s');
    }

    protected function beforeUpdate(): void
    {
        $this->updatedAt = date('Y-m-d H:i:s');
    }

    private function generateSlug(string $title): string
    {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);

        return trim($slug, '-');
    }
}
