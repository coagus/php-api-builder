<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\Http\Middleware\CorsMiddleware;
use Coagus\PhpApiBuilder\Http\Request;
use Coagus\PhpApiBuilder\Http\Response;

test('rejects wildcard origin combined with allowCredentials=true', function () {
    new CorsMiddleware(
        allowedOrigins: '*',
        allowCredentials: true,
    );
})->throws(RuntimeException::class, 'wildcard');

test('wildcard origin is allowed when credentials are disabled', function () {
    $cors = new CorsMiddleware(
        allowedOrigins: '*',
        allowCredentials: false,
    );

    $response = $cors->handle(
        new Request('GET', '/test', ['Origin' => 'https://example.com']),
        fn(Request $r) => new Response(['ok' => true])
    );

    expect($response->getHeader('Access-Control-Allow-Origin'))->toBe('https://example.com')
        ->and($response->getHeader('Access-Control-Allow-Credentials'))->toBeNull();
});

test('non-allowed origin does not receive Access-Control-Allow-Origin', function () {
    $cors = new CorsMiddleware(
        allowedOrigins: 'https://trusted.example',
        allowCredentials: false,
    );

    $response = $cors->handle(
        new Request('GET', '/test', ['Origin' => 'https://evil.example']),
        fn(Request $r) => new Response(['ok' => true])
    );

    expect($response->getHeader('Access-Control-Allow-Origin'))->toBeNull();
});
