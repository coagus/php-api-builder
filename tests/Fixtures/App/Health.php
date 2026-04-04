<?php

declare(strict_types=1);

namespace Tests\Fixtures\App;

use Coagus\PhpApiBuilder\Resource\Service;

class Health extends Service
{
    public function get(): void
    {
        $this->success(['status' => 'ok']);
    }
}
