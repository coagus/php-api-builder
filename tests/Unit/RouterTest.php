<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\Router;

test('parses URL to resource/id/action', function () {
    $router = new Router('Tests\\Fixtures\\App');

    $parsed = $router->parsePath('/api/v1/users');
    expect($parsed['resource'])->toBe('users')
        ->and($parsed['id'])->toBeNull()
        ->and($parsed['action'])->toBeNull();

    $parsed = $router->parsePath('/api/v1/users/123');
    expect($parsed['resource'])->toBe('users')
        ->and($parsed['id'])->toBe('123')
        ->and($parsed['action'])->toBeNull();

    $parsed = $router->parsePath('/api/v1/users/login');
    expect($parsed['resource'])->toBe('users')
        ->and($parsed['id'])->toBeNull()
        ->and($parsed['action'])->toBe('login');

    $parsed = $router->parsePath('/api/v1/users/123/orders');
    expect($parsed['resource'])->toBe('users')
        ->and($parsed['id'])->toBe('123')
        ->and($parsed['action'])->toBe('orders');
});

test('resolves HTTP method to class method', function () {
    $router = new Router('Tests\\Fixtures\\App');

    expect($router->resolveMethod('GET', null, \Tests\Fixtures\App\User::class))->toBe('get')
        ->and($router->resolveMethod('POST', null, \Tests\Fixtures\App\User::class))->toBe('post')
        ->and($router->resolveMethod('PUT', null, \Tests\Fixtures\App\User::class))->toBe('put')
        ->and($router->resolveMethod('PATCH', null, \Tests\Fixtures\App\User::class))->toBe('patch')
        ->and($router->resolveMethod('DELETE', null, \Tests\Fixtures\App\User::class))->toBe('delete');
});

test('resolves custom action to method', function () {
    $router = new Router('Tests\\Fixtures\\App');

    expect($router->resolveMethod('POST', 'login', \Tests\Fixtures\App\User::class))->toBe('postLogin');
});

test('returns null for unsupported custom action', function () {
    $router = new Router('Tests\\Fixtures\\App');

    expect($router->resolveMethod('POST', 'nonexistent', \Tests\Fixtures\App\User::class))->toBeNull();
});

test('invalid URL returns null', function () {
    $router = new Router('Tests\\Fixtures\\App');

    expect($router->parsePath('/other/path'))->toBeNull()
        ->and($router->parsePath('/api/v1'))->toBeNull()
        ->and($router->parsePath('/api/v1/'))->toBeNull();
});

test('discovers service class for resource name', function () {
    $router = new Router('Tests\\Fixtures\\App');

    $result = $router->resolve('GET', '/api/v1/users');
    expect($result)->not->toBeNull()
        ->and($result['class'])->toBe(\Tests\Fixtures\App\User::class);
});

test('discovers health service', function () {
    $router = new Router('Tests\\Fixtures\\App');

    // "healths" URL maps to "Health" class after singularization
    $result = $router->resolve('GET', '/api/v1/health');
    expect($result)->not->toBeNull()
        ->and($result['class'])->toBe(\Tests\Fixtures\App\Health::class);
});

test('returns null for non-existent resource', function () {
    $router = new Router('Tests\\Fixtures\\App');

    $result = $router->resolve('GET', '/api/v1/nonexistent');
    expect($result)->toBeNull();
});
