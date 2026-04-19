<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\Http\Middleware\MiddlewarePipeline;
use Coagus\PhpApiBuilder\Http\Middleware\RateLimitMiddleware;
use Coagus\PhpApiBuilder\Http\Middleware\RateLimitStore;
use Coagus\PhpApiBuilder\Http\Middleware\SecurityHeadersMiddleware;
use Coagus\PhpApiBuilder\Http\Request;
use Coagus\PhpApiBuilder\Http\Response;

$storePath = sys_get_temp_dir() . '/php-api-builder-ratelimit-test';

beforeEach(function () use ($storePath) {
    $this->store = new RateLimitStore($storePath);
    $this->store->flush();
    $this->handler = fn(Request $r) => new Response(['ok' => true]);
});

afterAll(function () use ($storePath) {
    $store = new RateLimitStore($storePath);
    $store->flush();
});

test('allows requests under the limit', function () {
    $middleware = new RateLimitMiddleware(limit: 5, windowSeconds: 60, store: $this->store);

    $response = $middleware->handle(new Request('GET', '/test'), $this->handler);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getHeader('X-RateLimit-Limit'))->toBe('5')
        ->and($response->getHeader('X-RateLimit-Remaining'))->toBe('4');
});

test('decrements remaining count on each request', function () {
    $middleware = new RateLimitMiddleware(limit: 3, windowSeconds: 60, store: $this->store);
    $request = new Request('GET', '/test');

    $r1 = $middleware->handle($request, $this->handler);
    $r2 = $middleware->handle($request, $this->handler);
    $r3 = $middleware->handle($request, $this->handler);

    expect($r1->getHeader('X-RateLimit-Remaining'))->toBe('2')
        ->and($r2->getHeader('X-RateLimit-Remaining'))->toBe('1')
        ->and($r3->getHeader('X-RateLimit-Remaining'))->toBe('0');
});

test('returns 429 when limit is exceeded', function () {
    $middleware = new RateLimitMiddleware(limit: 2, windowSeconds: 60, store: $this->store);
    $request = new Request('GET', '/test');

    $middleware->handle($request, $this->handler);
    $middleware->handle($request, $this->handler);
    $response = $middleware->handle($request, $this->handler);

    expect($response->getStatusCode())->toBe(429)
        ->and($response->getBody())->toMatchArray([
            'type' => 'about:blank',
            'title' => 'Too Many Requests',
            'status' => 429,
        ])
        ->and($response->getHeader('X-RateLimit-Remaining'))->toBe('0')
        ->and($response->getHeader('Retry-After'))->not->toBeNull();
});

test('adds rate limit headers to successful responses', function () {
    $middleware = new RateLimitMiddleware(limit: 10, windowSeconds: 60, store: $this->store);

    $response = $middleware->handle(new Request('GET', '/test'), $this->handler);

    expect($response->getHeader('X-RateLimit-Limit'))->toBe('10')
        ->and($response->getHeader('X-RateLimit-Remaining'))->toBe('9')
        ->and($response->getHeader('X-RateLimit-Reset'))->not->toBeNull();
});

test('resets count after clearing store', function () {
    $middleware = new RateLimitMiddleware(limit: 1, windowSeconds: 60, store: $this->store);
    $request = new Request('GET', '/test');

    $middleware->handle($request, $this->handler);
    $blocked = $middleware->handle($request, $this->handler);
    expect($blocked->getStatusCode())->toBe(429);

    $this->store->flush();

    $response = $middleware->handle($request, $this->handler);
    expect($response->getStatusCode())->toBe(200)
        ->and($response->getHeader('X-RateLimit-Remaining'))->toBe('0');
});

test('honors X-Forwarded-For only when REMOTE_ADDR is a trusted proxy', function () {
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $middleware = new RateLimitMiddleware(
        limit: 1,
        windowSeconds: 60,
        store: $this->store,
        trustedProxies: ['127.0.0.1'],
    );

    $r1 = $middleware->handle(
        new Request('GET', '/test', ['X-Forwarded-For' => '10.0.0.1']),
        $this->handler
    );
    $r2 = $middleware->handle(
        new Request('GET', '/test', ['X-Forwarded-For' => '10.0.0.2']),
        $this->handler
    );

    expect($r1->getStatusCode())->toBe(200)
        ->and($r2->getStatusCode())->toBe(200);
});

test('ignores X-Forwarded-For when the direct peer is not a trusted proxy', function () {
    $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
    $middleware = new RateLimitMiddleware(
        limit: 1,
        windowSeconds: 60,
        store: $this->store,
        trustedProxies: [],
    );

    $r1 = $middleware->handle(
        new Request('GET', '/test', ['X-Forwarded-For' => '10.0.0.1']),
        $this->handler
    );
    $r2 = $middleware->handle(
        new Request('GET', '/test', ['X-Forwarded-For' => '10.0.0.2']),
        $this->handler
    );

    // Both requests hit the same untrusted IP, so the second one MUST be blocked.
    expect($r1->getStatusCode())->toBe(200)
        ->and($r2->getStatusCode())->toBe(429);
});

test('works in pipeline with other middleware', function () {
    $pipeline = new MiddlewarePipeline();
    $pipeline->pipe(new RateLimitMiddleware(limit: 10, windowSeconds: 60, store: $this->store));
    $pipeline->pipe(new SecurityHeadersMiddleware());

    $response = $pipeline->process(
        new Request('GET', '/test'),
        $this->handler
    );

    expect($response->getHeader('X-RateLimit-Limit'))->toBe('10')
        ->and($response->getHeader('X-Content-Type-Options'))->toBe('nosniff');
});
