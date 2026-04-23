<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\Router;

/**
 * Exhaustive coverage of Router::parsePath for the five URL shapes the
 * router must support. Case (e) is the regression guard for UI-005:
 * prior to the fix, /resource/action/{id} silently dropped the id.
 */

beforeEach(function () {
    $this->router = new Router('Tests\\Fixtures\\App');
});

test('(a) /resource → resource with null id and null action', function () {
    $parsed = $this->router->parsePath('/api/v1/users');

    expect($parsed)->toBe([
        'resource' => 'users',
        'id' => null,
        'action' => null,
    ]);
});

test('(b) /resource/{numeric} → id is the numeric segment, action is null', function () {
    $parsed = $this->router->parsePath('/api/v1/users/42');

    expect($parsed)->toBe([
        'resource' => 'users',
        'id' => '42',
        'action' => null,
    ]);
});

test('(c) /resource/{numeric}/action → id=numeric, action=third segment', function () {
    $parsed = $this->router->parsePath('/api/v1/users/42/orders');

    expect($parsed)->toBe([
        'resource' => 'users',
        'id' => '42',
        'action' => 'orders',
    ]);
});

test('(d) /resource/action → non-numeric 2nd segment is the action, id null', function () {
    $parsed = $this->router->parsePath('/api/v1/users/login');

    expect($parsed)->toBe([
        'resource' => 'users',
        'id' => null,
        'action' => 'login',
    ]);
});

test('(e) /resource/action/{id} → preserves third segment as id (UI-005 regression guard)', function () {
    $parsed = $this->router->parsePath('/api/v1/me/sessions/123');

    expect($parsed)->toBe([
        'resource' => 'me',
        'id' => '123',
        'action' => 'sessions',
    ]);
});

test('(e2) /resource/action/{id} with non-numeric id segment also preserves it', function () {
    // Ids are not required to be numeric (UUIDs, slugs). Make that explicit.
    $parsed = $this->router->parsePath('/api/v1/me/sessions/abc-123');

    expect($parsed)->toBe([
        'resource' => 'me',
        'id' => 'abc-123',
        'action' => 'sessions',
    ]);
});
