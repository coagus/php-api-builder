<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\Helpers\SensitiveDataFilter;

test('password is replaced with PROTECTED', function () {
    $data = ['name' => 'Carlos', 'password' => 'secret123'];
    $filtered = SensitiveDataFilter::filter($data);

    expect($filtered['password'])->toBe('***PROTECTED***')
        ->and($filtered['name'])->toBe('Carlos');
});

test('token is replaced with PROTECTED', function () {
    $data = ['token' => 'abc123', 'access_token' => 'jwt-here', 'refresh_token' => 'refresh-here'];
    $filtered = SensitiveDataFilter::filter($data);

    expect($filtered['token'])->toBe('***PROTECTED***')
        ->and($filtered['access_token'])->toBe('***PROTECTED***')
        ->and($filtered['refresh_token'])->toBe('***PROTECTED***');
});

test('api_key is replaced with PROTECTED', function () {
    $data = ['api_key' => 'key-123', 'apikey' => 'key-456'];
    $filtered = SensitiveDataFilter::filter($data);

    expect($filtered['api_key'])->toBe('***PROTECTED***')
        ->and($filtered['apikey'])->toBe('***PROTECTED***');
});

test('normal fields are untouched', function () {
    $data = ['name' => 'Carlos', 'email' => 'carlos@test.com', 'age' => 30];
    $filtered = SensitiveDataFilter::filter($data);

    expect($filtered)->toBe($data);
});

test('nested arrays are filtered', function () {
    $data = [
        'user' => [
            'name' => 'Carlos',
            'password' => 'secret',
            'credentials' => [
                'api_key' => 'key-123',
                'token' => 'jwt-token',
            ],
        ],
        'email' => 'carlos@test.com',
    ];

    $filtered = SensitiveDataFilter::filter($data);

    expect($filtered['user']['name'])->toBe('Carlos')
        ->and($filtered['user']['password'])->toBe('***PROTECTED***')
        ->and($filtered['user']['credentials']['api_key'])->toBe('***PROTECTED***')
        ->and($filtered['user']['credentials']['token'])->toBe('***PROTECTED***')
        ->and($filtered['email'])->toBe('carlos@test.com');
});

test('authorization header is filtered', function () {
    $data = ['Authorization' => 'Bearer jwt-token-here'];
    $filtered = SensitiveDataFilter::filter($data);

    expect($filtered['Authorization'])->toBe('***PROTECTED***');
});
