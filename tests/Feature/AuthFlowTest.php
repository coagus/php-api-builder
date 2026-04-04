<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\Auth\Auth;
use Coagus\PhpApiBuilder\Http\Middleware\AuthMiddleware;
use Coagus\PhpApiBuilder\Http\Middleware\MiddlewarePipeline;
use Coagus\PhpApiBuilder\Http\Request;
use Coagus\PhpApiBuilder\Http\Response;

beforeEach(function () {
    Auth::reset();
    Auth::configure([
        'algorithm' => 'HS256',
        'secret' => 'test-secret-key-at-least-32-chars!!',
        'access_ttl' => 900,
        'issuer' => 'test-app',
        'audience' => 'test-api',
    ]);
});

function runWithAuth(Request $request, array $publicPaths = []): Response
{
    $pipeline = new MiddlewarePipeline();
    $pipeline->pipe(new AuthMiddleware($publicPaths));

    return $pipeline->process($request, fn(Request $r) => new Response(['data' => 'protected']));
}

test('protected resource rejects without token — 401', function () {
    $request = new Request('GET', '/api/v1/users');
    $response = runWithAuth($request);

    expect($response->getStatusCode())->toBe(401)
        ->and($response->getBody()['title'])->toBe('Unauthorized');
});

test('protected resource accepts valid token', function () {
    $token = Auth::generateAccessToken(['id' => 1, 'name' => 'Carlos']);
    $request = new Request('GET', '/api/v1/users', ['Authorization' => "Bearer {$token}"]);
    $response = runWithAuth($request);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getBody()['data'])->toBe('protected');
});

test('protected resource rejects expired token — 401', function () {
    $token = Auth::generateAccessToken(['id' => 1], [], -10);
    $request = new Request('GET', '/api/v1/users', ['Authorization' => "Bearer {$token}"]);
    $response = runWithAuth($request);

    expect($response->getStatusCode())->toBe(401);
});

test('public path is accessible without token', function () {
    $request = new Request('GET', '/api/v1/health');
    $response = runWithAuth($request, ['/api/v1/health']);

    expect($response->getStatusCode())->toBe(200);
});

test('OPTIONS request bypasses auth (CORS preflight)', function () {
    $request = new Request('OPTIONS', '/api/v1/users');
    $response = runWithAuth($request);

    expect($response->getStatusCode())->toBe(200);
});

test('refresh token cannot be used as access token', function () {
    $token = Auth::generateRefreshToken(42);
    $request = new Request('GET', '/api/v1/users', ['Authorization' => "Bearer {$token}"]);
    $response = runWithAuth($request);

    expect($response->getStatusCode())->toBe(401)
        ->and($response->getBody()['detail'])->toContain('Refresh tokens');
});

test('full flow: generate token → access protected → refresh', function () {
    // Generate access token
    $accessToken = Auth::generateAccessToken(
        ['id' => 42, 'name' => 'Carlos'],
        ['users:read']
    );

    // Access protected resource
    $request = new Request('GET', '/api/v1/users', ['Authorization' => "Bearer {$accessToken}"]);
    $response = runWithAuth($request);
    expect($response->getStatusCode())->toBe(200);

    // Decode and verify claims
    $decoded = Auth::decodeToken($accessToken);
    expect($decoded->data->id)->toBe(42)
        ->and($decoded->scopes)->toBe(['users:read']);
});
