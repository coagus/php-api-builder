<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\Auth\ScopeValidator;

test('valid scope passes', function () {
    $payload = (object) ['scopes' => ['users:read', 'orders:write']];

    expect(ScopeValidator::hasScope($payload, 'users:read'))->toBeTrue()
        ->and(ScopeValidator::hasScope($payload, 'orders:write'))->toBeTrue();
});

test('insufficient scope fails', function () {
    $payload = (object) ['scopes' => ['users:read']];

    expect(ScopeValidator::hasScope($payload, 'users:write'))->toBeFalse()
        ->and(ScopeValidator::hasScope($payload, 'admin'))->toBeFalse();
});

test('wildcard * scope matches everything', function () {
    $payload = (object) ['scopes' => ['*']];

    expect(ScopeValidator::hasScope($payload, 'users:read'))->toBeTrue()
        ->and(ScopeValidator::hasScope($payload, 'anything'))->toBeTrue();
});

test('resource wildcard matches all actions', function () {
    $payload = (object) ['scopes' => ['users:*']];

    expect(ScopeValidator::hasScope($payload, 'users:read'))->toBeTrue()
        ->and(ScopeValidator::hasScope($payload, 'users:write'))->toBeTrue()
        ->and(ScopeValidator::hasScope($payload, 'orders:read'))->toBeFalse();
});

test('hasAllScopes checks all required scopes', function () {
    $payload = (object) ['scopes' => ['users:read', 'orders:read']];

    expect(ScopeValidator::hasAllScopes($payload, ['users:read', 'orders:read']))->toBeTrue()
        ->and(ScopeValidator::hasAllScopes($payload, ['users:read', 'admin']))->toBeFalse();
});

test('hasAnyScope checks at least one scope', function () {
    $payload = (object) ['scopes' => ['users:read']];

    expect(ScopeValidator::hasAnyScope($payload, ['users:read', 'admin']))->toBeTrue()
        ->and(ScopeValidator::hasAnyScope($payload, ['admin', 'superadmin']))->toBeFalse();
});

test('empty scopes fail all checks', function () {
    $payload = (object) ['scopes' => []];

    expect(ScopeValidator::hasScope($payload, 'users:read'))->toBeFalse();
});
