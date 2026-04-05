<?php

declare(strict_types=1);

namespace App;

use Coagus\PhpApiBuilder\Attributes\Middleware;
use Coagus\PhpApiBuilder\Attributes\Route;
use Coagus\PhpApiBuilder\ORM\Connection;
use Coagus\PhpApiBuilder\Resource\APIDB;
use App\Entities\Post;
use App\Middleware\RequestLogger;

#[Route('posts')]
#[Middleware(RequestLogger::class)]
class PostService extends APIDB
{
    protected string $entity = Post::class;

    public function patchPublish(): void
    {
        $id = $this->request->resourceId ?? null;
        if (!$id) {
            $this->error('Bad Request', 400, 'Post ID is required.');
            return;
        }

        $post = Post::find((int) $id);
        if (!$post) {
            $this->error('Not Found', 404, 'Post not found.');
            return;
        }

        Connection::transaction(function () use ($post) {
            $post->status = 'published';
            $post->save();
        });

        $this->success($post);
    }

    public function patchArchive(): void
    {
        $id = $this->request->resourceId ?? null;
        if (!$id) {
            $this->error('Bad Request', 400, 'Post ID is required.');
            return;
        }

        $post = Post::find((int) $id);
        if (!$post) {
            $this->error('Not Found', 404, 'Post not found.');
            return;
        }

        $post->status = 'archived';
        $post->save();

        $this->success($post);
    }
}
