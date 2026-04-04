<?php

declare(strict_types=1);

namespace Tests\Fixtures\App;

use Coagus\PhpApiBuilder\Resource\APIDB;

class Role extends APIDB
{
    protected string $entity = \Tests\Fixtures\Entities\TestRole::class;
}
