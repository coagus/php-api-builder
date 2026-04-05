<?php

declare(strict_types=1);

namespace App;

use Coagus\PhpApiBuilder\Attributes\PublicResource;
use Coagus\PhpApiBuilder\Attributes\Route;
use Coagus\PhpApiBuilder\Resource\Service;
use App\Entities\{User, Post, Comment, Tag};

#[PublicResource]
#[Route('stats')]
class StatsService extends Service
{
    public function get(): void
    {
        $this->success([
            'users' => User::query()->count(),
            'posts' => Post::query()->count(),
            'published_posts' => Post::query()->where('status', 'published')->count(),
            'comments' => Comment::query()->count(),
            'tags' => Tag::query()->count(),
        ]);
    }
}
