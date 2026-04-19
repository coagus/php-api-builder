<?php

declare(strict_types=1);

namespace Tests\Fixtures\App;

use Coagus\PhpApiBuilder\Attributes\Middleware;
use Coagus\PhpApiBuilder\Http\Middleware\RateLimitMiddleware;
use Coagus\PhpApiBuilder\Resource\Service;

/**
 * Companion to `Alpha` — same per-route pattern but with a larger budget. Used
 * to demonstrate independent enforcement of per-endpoint limits.
 */
class Beta extends Service
{
    #[Middleware(RateLimitMiddleware::class, limit: 10, windowSeconds: 60)]
    public function get(): void
    {
        $this->success(['scope' => 'beta']);
    }
}
