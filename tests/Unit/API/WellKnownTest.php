<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\API;
use Coagus\PhpApiBuilder\Http\Request;
use Tests\Fixtures\App\Health;
use Tests\Fixtures\App\Jwk;
use Tests\Fixtures\App\OpenIdConfig;

test('registered well-known path resolves to declared handler', function () {
    $app = new API('Tests\\Fixtures\\App', '/api/v1', [
        '/.well-known/jwks.json' => [Jwk::class, 'get'],
    ]);

    $response = $app->run(new Request('GET', '/.well-known/jwks.json'));

    expect($response->getStatusCode())->toBe(200);
    $body = $response->getBody();
    expect($body)->toHaveKey('data')
        ->and($body['data'])->toHaveKey('keys')
        ->and($body['data']['keys'][0]['kty'])->toBe('RSA')
        ->and($body['data']['keys'][0]['kid'])->toBe('test-key-1');
});

test('unregistered .well-known path returns 404 problem+json', function () {
    $app = new API('Tests\\Fixtures\\App', '/api/v1', [
        '/.well-known/jwks.json' => [Jwk::class, 'get'],
    ]);

    $response = $app->run(new Request('GET', '/.well-known/openid-configuration'));

    expect($response->getStatusCode())->toBe(404)
        ->and($response->getHeader('Content-Type'))->toBe('application/problem+json; charset=utf-8');
    $body = $response->getBody();
    expect($body['title'])->toBe('Not Found')
        ->and($body['status'])->toBe(404)
        ->and($body)->toHaveKey('type');
});

test('wellKnown resolution precedes apiPrefix matching', function () {
    // /api/v1/health would normally route to the Health fixture service.
    // Registering OpenIdConfig at that path proves wellKnown short-circuits first.
    $app = new API('Tests\\Fixtures\\App', '/api/v1', [
        '/api/v1/health' => [OpenIdConfig::class, 'get'],
    ]);

    $response = $app->run(new Request('GET', '/api/v1/health'));

    expect($response->getStatusCode())->toBe(200);
    $body = $response->getBody();
    // OpenIdConfig emits `source: well-known`; Health emits `status: ok`.
    expect($body['data'])->toHaveKey('source')
        ->and($body['data']['source'])->toBe('well-known')
        ->and($body['data'])->not->toHaveKey('status');
});

test('empty wellKnown preserves existing router behavior', function () {
    $app = new API('Tests\\Fixtures\\App');

    $response = $app->run(new Request('GET', '/api/v1/health'));

    expect($response->getStatusCode())->toBe(200);
    $body = $response->getBody();
    expect($body['data']['status'])->toBe('ok');
});

test('non-matching wellKnown map falls through to router unchanged', function () {
    $app = new API('Tests\\Fixtures\\App', '/api/v1', [
        '/.well-known/jwks.json' => [Jwk::class, 'get'],
    ]);

    $response = $app->run(new Request('GET', '/api/v1/health'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()['data']['status'])->toBe('ok');
});

test('trailing slash on request path matches wellKnown entry', function () {
    $app = new API('Tests\\Fixtures\\App', '/api/v1', [
        '/.well-known/jwks.json' => [Jwk::class, 'get'],
    ]);

    $response = $app->run(new Request('GET', '/.well-known/jwks.json/'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()['data']['keys'][0]['kid'])->toBe('test-key-1');
});

test('constructor throws when wellKnown class does not exist', function () {
    new API('Tests\\Fixtures\\App', '/api/v1', [
        '/.well-known/jwks.json' => ['Tests\\Fixtures\\App\\DoesNotExist', 'get'],
    ]);
})->throws(\InvalidArgumentException::class, 'does not exist');

test('constructor throws when wellKnown method is not callable on class', function () {
    new API('Tests\\Fixtures\\App', '/api/v1', [
        '/.well-known/jwks.json' => [Jwk::class, 'missingMethod'],
    ]);
})->throws(\InvalidArgumentException::class, 'is not callable');

test('constructor throws when wellKnown tuple is malformed', function () {
    new API('Tests\\Fixtures\\App', '/api/v1', [
        '/.well-known/jwks.json' => [Jwk::class],
    ]);
})->throws(\InvalidArgumentException::class, 'tuple');

test('constructor throws when wellKnown path does not start with slash', function () {
    new API('Tests\\Fixtures\\App', '/api/v1', [
        'well-known/jwks.json' => [Jwk::class, 'get'],
    ]);
})->throws(\InvalidArgumentException::class, 'paths must be non-empty');

test('canonical JWKS exposure via both /.well-known and apiPrefix routes', function () {
    // Canonical JWKS exposure pattern from UI-004: the alpha.22 workaround at
    // /api/v1/jwks coexists with the RFC 8615 path — both are served by Jwk::get.
    // The wellKnown map handles the canonical path; the Router handles /api/v1/jwks
    // via the existing singularization heuristic ("jwks" → "Jwk").
    $app = new API('Tests\\Fixtures\\App', '/api/v1', [
        '/.well-known/jwks.json' => [Jwk::class, 'get'],
    ]);

    $wellKnown = $app->run(new Request('GET', '/.well-known/jwks.json'));
    $routed = $app->run(new Request('GET', '/api/v1/jwks'));

    expect($wellKnown->getStatusCode())->toBe(200);
    expect($routed->getStatusCode())->toBe(200);
    // Both dispatch paths land on the same handler and produce the same JWKS.
    expect($wellKnown->getBody()['data']['keys'][0]['kid'])->toBe('test-key-1');
    expect($routed->getBody()['data']['keys'][0]['kid'])->toBe('test-key-1');
});

test('X-Request-ID header is attached to well-known responses', function () {
    $app = new API('Tests\\Fixtures\\App', '/api/v1', [
        '/.well-known/jwks.json' => [Jwk::class, 'get'],
    ]);

    $response = $app->run(new Request('GET', '/.well-known/jwks.json'));

    expect($response->getHeader('X-Request-ID'))->not->toBeNull()
        ->and(strlen($response->getHeader('X-Request-ID')))->toBeGreaterThan(0);
});
