<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\Auth\Auth;

beforeEach(function () {
    Auth::reset();
    Auth::configure([
        'algorithm' => 'HS256',
        'secret' => 'test-secret-key-at-least-32-chars!!',
        'access_ttl' => 900,
        'refresh_ttl' => 604800,
        'issuer' => 'test-app',
        'audience' => 'test-api',
    ]);
});

test('generates access token with correct claims', function () {
    $token = Auth::generateAccessToken(
        ['id' => 42, 'name' => 'Carlos', 'role' => 'admin'],
        ['users:read', 'orders:write']
    );

    expect($token)->toBeString()->not->toBeEmpty();

    $decoded = Auth::validateToken($token);

    expect($decoded->iss)->toBe('test-app')
        ->and($decoded->aud)->toBe('test-api')
        ->and($decoded->sub)->toBe('user:42')
        ->and($decoded->scopes)->toBe(['users:read', 'orders:write'])
        ->and($decoded->data->id)->toBe(42)
        ->and($decoded->data->name)->toBe('Carlos')
        ->and($decoded->exp)->toBeGreaterThan(time())
        ->and($decoded->jti)->toBeString();
});

test('validates a valid token', function () {
    $token = Auth::generateAccessToken(['id' => 1]);
    $decoded = Auth::validateToken($token);

    expect($decoded->sub)->toBe('user:1');
});

test('rejects expired token', function () {
    $token = Auth::generateAccessToken(['id' => 1], [], -10); // expired 10 seconds ago

    Auth::validateToken($token);
})->throws(RuntimeException::class, 'Invalid token');

test('rejects token with invalid signature', function () {
    $token = Auth::generateAccessToken(['id' => 1]);

    // Tamper with the token
    $parts = explode('.', $token);
    $parts[2] = str_repeat('a', strlen($parts[2]));
    $tampered = implode('.', $parts);

    Auth::validateToken($tampered);
})->throws(RuntimeException::class, 'Invalid token');

test('generates refresh token with correct type', function () {
    $token = Auth::generateRefreshToken(42);
    $decoded = Auth::validateToken($token);

    expect($decoded->type)->toBe('refresh')
        ->and($decoded->sub)->toBe('user:42')
        ->and($decoded->family_id)->toBeString();
});

test('generates refresh token with specific family_id', function () {
    $familyId = 'test-family-123';
    $token = Auth::generateRefreshToken(42, $familyId);
    $decoded = Auth::validateToken($token);

    expect($decoded->family_id)->toBe($familyId);
});

test('throws if not configured', function () {
    Auth::reset();
    Auth::generateAccessToken(['id' => 1]);
})->throws(RuntimeException::class, 'Auth not configured');

test('rejects token whose iss claim does not match configured issuer', function () {
    // Craft a token with a different issuer config, then validate under ours.
    Auth::reset();
    Auth::configure([
        'algorithm' => 'HS256',
        'secret' => 'test-secret-key-at-least-32-chars!!',
        'issuer' => 'OTHER-issuer',
        'audience' => 'test-api',
    ]);
    $foreign = Auth::generateAccessToken(['id' => 1]);

    Auth::reset();
    Auth::configure([
        'algorithm' => 'HS256',
        'secret' => 'test-secret-key-at-least-32-chars!!',
        'issuer' => 'test-app',
        'audience' => 'test-api',
    ]);

    expect(fn() => Auth::validateToken($foreign))
        ->toThrow(RuntimeException::class, 'Invalid token issuer');
});

test('rejects token whose aud claim does not match configured audience', function () {
    Auth::reset();
    Auth::configure([
        'algorithm' => 'HS256',
        'secret' => 'test-secret-key-at-least-32-chars!!',
        'issuer' => 'test-app',
        'audience' => 'OTHER-aud',
    ]);
    $foreign = Auth::generateAccessToken(['id' => 1]);

    Auth::reset();
    Auth::configure([
        'algorithm' => 'HS256',
        'secret' => 'test-secret-key-at-least-32-chars!!',
        'issuer' => 'test-app',
        'audience' => 'test-api',
    ]);

    expect(fn() => Auth::validateToken($foreign))
        ->toThrow(RuntimeException::class, 'Invalid token audience');
});
