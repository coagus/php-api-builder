<?php

declare(strict_types=1);

namespace Tests\Fixtures\App;

use Coagus\PhpApiBuilder\Resource\Service;

/**
 * Fixture service used to prove that `wellKnown` resolution runs before the
 * `$apiPrefix` matching — registering this handler at `/api/v1/health` (a path
 * that would otherwise be claimed by the Health fixture) lets the test
 * distinguish which dispatcher path served the response.
 */
class OpenIdConfig extends Service
{
    public function get(): void
    {
        $this->success([
            'issuer' => 'https://example.test',
            'jwks_uri' => 'https://example.test/.well-known/jwks.json',
            'source' => 'well-known',
        ]);
    }
}
