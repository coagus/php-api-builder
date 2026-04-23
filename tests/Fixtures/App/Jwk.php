<?php

declare(strict_types=1);

namespace Tests\Fixtures\App;

use Coagus\PhpApiBuilder\Resource\Service;

/**
 * Fixture service modelling the canonical RFC 7517 JWKS handler used by
 * `ag-api` and referenced in UI-004. Mapped in tests via the API::$wellKnown
 * constructor argument to `/.well-known/jwks.json`.
 */
class Jwk extends Service
{
    public function get(): void
    {
        $this->success([
            'keys' => [
                [
                    'kty' => 'RSA',
                    'use' => 'sig',
                    'alg' => 'RS256',
                    'kid' => 'test-key-1',
                    'n' => 'fixture-modulus',
                    'e' => 'AQAB',
                ],
            ],
        ]);
    }
}
