<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Coagus\PhpApiBuilder\Resource\APIDB;

class TestArticleApidb extends APIDB
{
    protected string $entity = \Tests\Fixtures\Entities\TestArticle::class;
}
