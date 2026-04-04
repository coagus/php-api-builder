<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Coagus\PhpApiBuilder\Resource\APIDB;

class TestUserApidb extends APIDB
{
    protected string $entity = \Tests\Fixtures\Entities\TestUser::class;
}
