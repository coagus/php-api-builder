<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\Http\Request;

test('parses method correctly', function () {
    $request = new Request('GET', '/api/v1/users');
    expect($request->getMethod())->toBe('GET');
});

test('parses method case-insensitively', function () {
    $request = new Request('post', '/api/v1/users');
    expect($request->getMethod())->toBe('POST');
});

test('parses URI and path', function () {
    $request = new Request('GET', '/api/v1/users?page=2&per_page=10');

    expect($request->getUri())->toBe('/api/v1/users?page=2&per_page=10')
        ->and($request->getPath())->toBe('/api/v1/users');
});

test('extracts query params', function () {
    $request = new Request('GET', '/api/v1/users?page=2&per_page=10&active=true');

    expect($request->getQueryParams())->toBe(['page' => '2', 'per_page' => '10', 'active' => 'true'])
        ->and($request->getQueryParam('page'))->toBe('2')
        ->and($request->getQueryParam('missing', 'default'))->toBe('default');
});

test('parses JSON body', function () {
    $request = new Request('POST', '/api/v1/users', [], '{"name":"Carlos","email":"carlos@test.com"}');

    $input = $request->getInput();
    expect($input)->not->toBeNull()
        ->and($input->name)->toBe('Carlos')
        ->and($input->email)->toBe('carlos@test.com');
});

test('getInput returns null for empty body', function () {
    $request = new Request('GET', '/api/v1/users', [], '');
    expect($request->getInput())->toBeNull();
});

test('getRawBody returns array', function () {
    $request = new Request('POST', '/api/v1/users', [], '{"name":"Carlos"}');
    expect($request->getRawBody())->toBe(['name' => 'Carlos']);
});

test('parses custom headers', function () {
    $request = new Request('GET', '/api/v1/users', ['Authorization' => 'Bearer abc123', 'X-Custom' => 'test']);

    expect($request->getHeader('Authorization'))->toBe('Bearer abc123')
        ->and($request->getHeader('x-custom'))->toBe('test')
        ->and($request->getHeader('nonexistent'))->toBeNull();
});

test('getBearerToken extracts token from Authorization header', function () {
    $request = new Request('GET', '/', ['Authorization' => 'Bearer my-jwt-token']);
    expect($request->getBearerToken())->toBe('my-jwt-token');
});

test('getBearerToken returns null without Bearer prefix', function () {
    $request = new Request('GET', '/', ['Authorization' => 'Basic abc123']);
    expect($request->getBearerToken())->toBeNull();
});
