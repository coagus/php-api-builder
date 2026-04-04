<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\Http\Middleware\CorsMiddleware;
use Coagus\PhpApiBuilder\Http\Middleware\MiddlewareInterface;
use Coagus\PhpApiBuilder\Http\Middleware\MiddlewarePipeline;
use Coagus\PhpApiBuilder\Http\Middleware\SecurityHeadersMiddleware;
use Coagus\PhpApiBuilder\Http\Request;
use Coagus\PhpApiBuilder\Http\Response;

class LogMiddleware implements MiddlewareInterface
{
    public static array $log = [];

    public function handle(Request $request, callable $next): Response
    {
        self::$log[] = 'before';
        $response = $next($request);
        self::$log[] = 'after';

        return $response;
    }
}

class SecondMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        LogMiddleware::$log[] = 'second-before';
        $response = $next($request);
        LogMiddleware::$log[] = 'second-after';

        return $response;
    }
}

class ShortCircuitMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        return new Response(['blocked' => true], 403);
    }
}

beforeEach(function () {
    LogMiddleware::$log = [];
});

test('middlewares execute in correct order', function () {
    $pipeline = new MiddlewarePipeline();
    $pipeline->pipe(new LogMiddleware());
    $pipeline->pipe(new SecondMiddleware());

    $response = $pipeline->process(
        new Request('GET', '/test'),
        fn(Request $r) => new Response(['ok' => true])
    );

    expect(LogMiddleware::$log)->toBe(['before', 'second-before', 'second-after', 'after'])
        ->and($response->getBody())->toBe(['ok' => true]);
});

test('middleware can short-circuit the pipeline', function () {
    $pipeline = new MiddlewarePipeline();
    $pipeline->pipe(new ShortCircuitMiddleware());
    $pipeline->pipe(new LogMiddleware());

    $response = $pipeline->process(
        new Request('GET', '/test'),
        fn(Request $r) => new Response(['ok' => true])
    );

    expect($response->getStatusCode())->toBe(403)
        ->and($response->getBody())->toBe(['blocked' => true])
        ->and(LogMiddleware::$log)->toBe([]);
});

test('SecurityHeadersMiddleware adds correct headers', function () {
    $pipeline = new MiddlewarePipeline();
    $pipeline->pipe(new SecurityHeadersMiddleware());

    $response = $pipeline->process(
        new Request('GET', '/test'),
        fn(Request $r) => new Response(['ok' => true])
    );

    expect($response->getHeader('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->getHeader('X-Frame-Options'))->toBe('DENY')
        ->and($response->getHeader('Cache-Control'))->toBe('no-store')
        ->and($response->getHeader('Referrer-Policy'))->toBe('strict-origin-when-cross-origin')
        ->and($response->getHeader('Permissions-Policy'))->toBe('camera=(), microphone=(), geolocation=()');
});

test('CorsMiddleware responds to OPTIONS with 204', function () {
    $cors = new CorsMiddleware('*');

    $response = $cors->handle(
        new Request('OPTIONS', '/test', ['Origin' => 'https://example.com']),
        fn(Request $r) => new Response(['ok' => true])
    );

    expect($response->getStatusCode())->toBe(204)
        ->and($response->getHeader('Access-Control-Allow-Origin'))->toBe('https://example.com')
        ->and($response->getHeader('Access-Control-Allow-Methods'))->toContain('GET');
});

test('CorsMiddleware passes non-OPTIONS requests through', function () {
    $cors = new CorsMiddleware('https://example.com');

    $response = $cors->handle(
        new Request('GET', '/test', ['Origin' => 'https://example.com']),
        fn(Request $r) => new Response(['ok' => true], 200)
    );

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getHeader('Access-Control-Allow-Origin'))->toBe('https://example.com');
});

test('empty pipeline passes through to handler', function () {
    $pipeline = new MiddlewarePipeline();

    $response = $pipeline->process(
        new Request('GET', '/test'),
        fn(Request $r) => new Response(['direct' => true])
    );

    expect($response->getBody())->toBe(['direct' => true]);
});
