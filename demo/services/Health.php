<?php

declare(strict_types=1);

namespace DemoApi;

use Coagus\PhpApiBuilder\Attributes\PublicResource;
use Coagus\PhpApiBuilder\Resource\Service;

#[PublicResource]
class Health extends Service
{
    public function get(): void
    {
        $this->success([
            'status' => 'ok',
            'version' => '2.0.0',
            'timestamp' => time(),
        ]);
    }
}
