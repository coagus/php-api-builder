<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\API;
use Coagus\PhpApiBuilder\Http\Request;

/**
 * End-to-end coverage for nested-action URLs of the shape
 * `/resource/action/{id}` (UI-005). Prior to alpha.24 the 3rd segment
 * was silently discarded by the router; these tests exercise a real
 * dispatch so a future regression would flip them red.
 */

test('DELETE /api/v1/me/sessions/123 dispatches to deleteSessions with resourceId=123', function () {
    $app = new API('Tests\\Fixtures\\App');

    $response = $app->run(new Request('DELETE', '/api/v1/me/sessions/123'));

    expect($response->getStatusCode())->toBe(200);
    $body = $response->getBody();
    expect($body['data'])->toMatchArray([
        'action' => 'sessions',
        'resourceId' => '123',
        'closed' => true,
    ]);
});

test('GET /api/v1/me/sessions/abc-123 preserves a non-numeric id', function () {
    // Ids need not be numeric — UUIDs / slugs must also survive.
    $app = new API('Tests\\Fixtures\\App');

    $response = $app->run(new Request('GET', '/api/v1/me/sessions/abc-123'));

    expect($response->getStatusCode())->toBe(200);
    $body = $response->getBody();
    expect($body['data'])->toMatchArray([
        'action' => 'sessions',
        'resourceId' => 'abc-123',
    ]);
});

test('DELETE /api/v1/me/sessions without an id still dispatches to the collection handler', function () {
    // Shape (d): `/resource/action` — id must remain null.
    $app = new API('Tests\\Fixtures\\App');

    $response = $app->run(new Request('DELETE', '/api/v1/me/sessions'));

    expect($response->getStatusCode())->toBe(200);
    $body = $response->getBody();
    expect($body['data']['action'])->toBe('sessions')
        ->and($body['data']['resourceId'])->toBeNull();
});
