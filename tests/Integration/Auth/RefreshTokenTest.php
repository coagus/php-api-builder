<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\Auth\Auth;
use Coagus\PhpApiBuilder\Auth\RefreshTokenStore;
use Coagus\PhpApiBuilder\ORM\Connection;

beforeEach(function () {
    Connection::reset();
    Connection::configure(['driver' => 'sqlite', 'database' => ':memory:']);
    RefreshTokenStore::createTable();

    Auth::reset();
    Auth::configure([
        'algorithm' => 'HS256',
        'secret' => 'test-secret-key-at-least-32-chars!!',
        'refresh_ttl' => 604800,
        'issuer' => 'test-app',
        'audience' => 'test-api',
    ]);
});

test('rotation: use refresh token and get a new pair', function () {
    // Create initial refresh token
    $refreshToken = Auth::generateRefreshToken(42);
    $decoded = Auth::decodeToken($refreshToken);
    $tokenHash = hash('sha256', $refreshToken);

    RefreshTokenStore::store($decoded->jti, 42, $tokenHash, $decoded->family_id, $decoded->exp);

    // Rotate
    $result = RefreshTokenStore::rotateToken($refreshToken);

    expect($result['refreshToken'])->toBeString()->not->toBe($refreshToken)
        ->and($result['userId'])->toBe(42)
        ->and($result['familyId'])->toBe($decoded->family_id);

    // Old token should be revoked
    $oldRecord = RefreshTokenStore::findByHash($tokenHash);
    expect((int) $oldRecord['revoked'])->toBe(1);
});

test('reuse detection: using already-rotated token revokes family', function () {
    // Create initial refresh token
    $refreshToken = Auth::generateRefreshToken(42);
    $decoded = Auth::decodeToken($refreshToken);
    $tokenHash = hash('sha256', $refreshToken);

    RefreshTokenStore::store($decoded->jti, 42, $tokenHash, $decoded->family_id, $decoded->exp);

    // First rotation — succeeds
    $result = RefreshTokenStore::rotateToken($refreshToken);

    // Second rotation with SAME token — reuse detected
    expect(fn() => RefreshTokenStore::rotateToken($refreshToken))
        ->toThrow(RuntimeException::class, 'reuse detected');

    // All tokens in family should be revoked
    $rows = Connection::getInstance()->query(
        "SELECT * FROM refresh_tokens WHERE family_id = ? AND revoked = 0",
        [$decoded->family_id]
    );
    expect($rows)->toHaveCount(0);
});

test('expired refresh token is rejected', function () {
    // Create a valid token but store it with past expiration
    $refreshToken = Auth::generateRefreshToken(42);
    $tokenHash = hash('sha256', $refreshToken);

    // Manually store with past expiration time
    Connection::getInstance()->execute(
        "INSERT INTO refresh_tokens (id, user_id, token_hash, family_id, expires_at) VALUES (?, ?, ?, ?, ?)",
        ['test-id', 42, $tokenHash, 'test-family', date('Y-m-d H:i:s', time() - 3600)]
    );

    expect(fn() => RefreshTokenStore::rotateToken($refreshToken))
        ->toThrow(RuntimeException::class, 'expired');
});
