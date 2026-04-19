<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\API;
use Coagus\PhpApiBuilder\Http\Middleware\RateLimitStore;
use Coagus\PhpApiBuilder\Http\Request;

beforeEach(function () {
    // The default RateLimitMiddleware store uses sys_get_temp_dir + a fixed
    // subdirectory; flushing between tests keeps counters deterministic.
    (new RateLimitStore())->flush();
});

function runPerRoute(API $app, string $path): int
{
    return $app->run(new Request('GET', $path))->getStatusCode();
}

test('per-route #[Middleware] enforces its own rate limit on /alphas', function () {
    $app = new API('Tests\\Fixtures\\App');

    $codes = [];
    for ($i = 0; $i < 5; $i++) {
        $codes[] = runPerRoute($app, '/api/v1/alphas');
    }

    // 3 requests allowed, then 429 for the next two.
    expect(array_slice($codes, 0, 3))->toBe([200, 200, 200])
        ->and($codes[3])->toBe(429)
        ->and($codes[4])->toBe(429);
});

test('/betas has a larger budget enforced by its own #[Middleware]', function () {
    $app = new API('Tests\\Fixtures\\App');

    // Eight hits fit under the limit=10 configured on Beta::get().
    $codes = [];
    for ($i = 0; $i < 8; $i++) {
        $codes[] = runPerRoute($app, '/api/v1/betas');
    }

    expect(array_unique($codes))->toBe([200]);
});

test('rate-limit response body follows RFC 7807', function () {
    $app = new API('Tests\\Fixtures\\App');

    for ($i = 0; $i < 3; $i++) {
        $app->run(new Request('GET', '/api/v1/alphas'));
    }
    $response = $app->run(new Request('GET', '/api/v1/alphas'));

    expect($response->getStatusCode())->toBe(429)
        ->and($response->getBody())->toMatchArray([
            'title' => 'Too Many Requests',
            'status' => 429,
        ])
        ->and($response->getHeader('Retry-After'))->not->toBeNull();
});
