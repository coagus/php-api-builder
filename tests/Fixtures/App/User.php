<?php

declare(strict_types=1);

namespace Tests\Fixtures\App;

use Coagus\PhpApiBuilder\Resource\APIDB;

class User extends APIDB
{
    protected string $entity = \Tests\Fixtures\Entities\TestUser::class;

    public function postLogin(): void
    {
        $input = $this->getInput();
        $this->success(['token' => 'fake-jwt-token', 'email' => $input->email ?? 'unknown']);
    }
}
