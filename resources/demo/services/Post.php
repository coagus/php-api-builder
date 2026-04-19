<?php

declare(strict_types=1);

namespace App;

use Coagus\PhpApiBuilder\Attributes\Middleware;
use Coagus\PhpApiBuilder\ORM\Connection;
use Coagus\PhpApiBuilder\Resource\APIDB;
use App\Entities\Post as PostEntity;
use App\Middleware\RequestLogger;

#[Middleware(RequestLogger::class)]
class Post extends APIDB
{
    protected string $entity = PostEntity::class;

    public function patchPublish(): void
    {
        $id = $this->resourceId;
        if (!$id) {
            $this->error('Bad Request', 400, 'Post ID is required.');
            return;
        }

        $post = PostEntity::find((int) $id);
        if (!$post) {
            $this->error('Not Found', 404, 'Post not found.');
            return;
        }

        Connection::getInstance()->transaction(function () use ($post) {
            $post->status = 'published';
            $post->save();
        });

        $this->success($post);
    }

    public function patchArchive(): void
    {
        $id = $this->resourceId;
        if (!$id) {
            $this->error('Bad Request', 400, 'Post ID is required.');
            return;
        }

        $post = PostEntity::find((int) $id);
        if (!$post) {
            $this->error('Not Found', 404, 'Post not found.');
            return;
        }

        $post->status = 'archived';
        $post->save();

        $this->success($post);
    }
}
