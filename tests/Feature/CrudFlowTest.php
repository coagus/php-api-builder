<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\API;
use Coagus\PhpApiBuilder\Http\Request;
use Coagus\PhpApiBuilder\ORM\Connection;
use Tests\Fixtures\Entities\TestRole;
use Tests\Fixtures\Entities\TestUser;

beforeEach(function () {
    Connection::reset();
    Connection::configure(['driver' => 'sqlite', 'database' => ':memory:']);
    $db = Connection::getInstance();
    $db->exec(file_get_contents(__DIR__ . '/../Fixtures/migrations.sql'));
    TestUser::clearMetadataCache();
    TestRole::clearMetadataCache();
});

function api(): API
{
    return new API('Tests\\Fixtures\\App');
}

test('end-to-end CRUD flow: POST → GET list → GET by ID → PATCH → DELETE', function () {
    $app = api();

    // POST create
    $response = $app->run(new Request('POST', '/api/v1/roles', [], '{"name":"Admin"}'));
    expect($response->getStatusCode())->toBe(201);
    $body = $response->getBody();
    $roleId = $body['data']['id'];
    expect($roleId)->toBeGreaterThan(0)
        ->and($body['data']['name'])->toBe('Admin');

    // POST create another
    $app2 = api();
    $response = $app2->run(new Request('POST', '/api/v1/roles', [], '{"name":"User"}'));
    expect($response->getStatusCode())->toBe(201);

    // GET list
    $app3 = api();
    $response = $app3->run(new Request('GET', '/api/v1/roles'));
    expect($response->getStatusCode())->toBe(200);
    $body = $response->getBody();
    expect($body['data'])->toHaveCount(2)
        ->and($body['meta']['total'])->toBe(2);

    // GET by ID
    $app4 = api();
    $response = $app4->run(new Request('GET', "/api/v1/roles/{$roleId}"));
    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()['data']['name'])->toBe('Admin');

    // PATCH update
    $app5 = api();
    $response = $app5->run(new Request('PATCH', "/api/v1/roles/{$roleId}", [], '{"name":"SuperAdmin"}'));
    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()['data']['name'])->toBe('SuperAdmin');

    // DELETE
    $app6 = api();
    $response = $app6->run(new Request('DELETE', "/api/v1/roles/{$roleId}"));
    expect($response->getStatusCode())->toBe(204);

    // GET after delete → 404
    $app7 = api();
    $response = $app7->run(new Request('GET', "/api/v1/roles/{$roleId}"));
    expect($response->getStatusCode())->toBe(404);
});

test('custom action: POST login', function () {
    $app = api();
    $response = $app->run(new Request('POST', '/api/v1/users/login', [], '{"email":"test@test.com","password":"pass"}'));

    expect($response->getStatusCode())->toBe(200);
    $body = $response->getBody();
    expect($body['data']['token'])->toBe('fake-jwt-token')
        ->and($body['data']['email'])->toBe('test@test.com');
});

test('service endpoint works', function () {
    $app = api();
    $response = $app->run(new Request('GET', '/api/v1/health'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()['data']['status'])->toBe('ok');
});

test('response includes X-Request-ID header', function () {
    $app = api();
    $response = $app->run(new Request('GET', '/api/v1/health'));

    expect($response->getHeader('X-Request-ID'))->not->toBeNull()
        ->and(strlen($response->getHeader('X-Request-ID')))->toBe(16);
});
