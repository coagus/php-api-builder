<?php

declare(strict_types=1);

namespace Tests\Fixtures\App;

use Coagus\PhpApiBuilder\Attributes\Middleware;
use Coagus\PhpApiBuilder\Http\Middleware\RateLimitMiddleware;
use Coagus\PhpApiBuilder\Resource\Service;

/**
 * A resource whose GET is rate-limited to 3 hits per window via a per-route
 * `#[Middleware]` attribute. Paired with `Beta` in feature tests to prove that
 * per-endpoint limits are enforced independently of each other.
 */
class Alpha extends Service
{
    #[Middleware(RateLimitMiddleware::class, limit: 3, windowSeconds: 60)]
    public function get(): void
    {
        $this->success(['scope' => 'alpha']);
    }
}
