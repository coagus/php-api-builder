<?php

declare(strict_types=1);

namespace App;

use Coagus\PhpApiBuilder\Attributes\PublicResource;
use Coagus\PhpApiBuilder\Resource\Service;
use App\Entities\{User, Post as PostEntity, Comment, Tag};

#[PublicResource]
class Stat extends Service
{
    public function get(): void
    {
        $this->success([
            'users' => User::query()->count(),
            'posts' => PostEntity::query()->count(),
            'published_posts' => PostEntity::query()->where('status', 'published')->count(),
            'comments' => Comment::query()->count(),
            'tags' => Tag::query()->count(),
        ]);
    }
}
