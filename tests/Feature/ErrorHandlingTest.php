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

test('non-existent resource returns 404 RFC 7807', function () {
    $app = new API('Tests\\Fixtures\\App');
    $response = $app->run(new Request('GET', '/api/v1/nonexistent'));

    $body = $response->getBody();
    expect($response->getStatusCode())->toBe(404)
        ->and($body['title'])->toBe('Not Found')
        ->and($body['status'])->toBe(404)
        ->and($body)->toHaveKey('type');
});

test('unsupported custom action returns 405 RFC 7807', function () {
    $app = new API('Tests\\Fixtures\\App');
    $response = $app->run(new Request('POST', '/api/v1/roles/nonexistent-action'));

    $body = $response->getBody();
    expect($response->getStatusCode())->toBe(405)
        ->and($body['title'])->toBe('Method Not Allowed')
        ->and($body['status'])->toBe(405);
});

test('validation failure returns 422 RFC 7807', function () {
    $app = new API('Tests\\Fixtures\\App');
    $response = $app->run(new Request('POST', '/api/v1/users', [], '{"password":"pass"}'));

    $body = $response->getBody();
    expect($response->getStatusCode())->toBe(422)
        ->and($body['title'])->toBe('Validation Error')
        ->and($body['status'])->toBe(422)
        ->and($body)->toHaveKey('errors');
});

test('GET non-existent ID returns 404', function () {
    $app = new API('Tests\\Fixtures\\App');
    $response = $app->run(new Request('GET', '/api/v1/roles/999'));

    expect($response->getStatusCode())->toBe(404)
        ->and($response->getBody()['title'])->toBe('Resource not found');
});

test('error responses include requestId', function () {
    $app = new API('Tests\\Fixtures\\App');
    $response = $app->run(new Request('GET', '/api/v1/nonexistent'));

    expect($response->getHeader('X-Request-ID'))->not->toBeNull();
});

test('error responses advertise application/problem+json per RFC 7807', function () {
    $app = new API('Tests\\Fixtures\\App');

    $notFound = $app->run(new Request('GET', '/api/v1/nonexistent'));
    $validation = $app->run(new Request('POST', '/api/v1/users', [], '{"password":"pass"}'));

    expect($notFound->getHeader('Content-Type'))->toBe('application/problem+json; charset=utf-8')
        ->and($validation->getHeader('Content-Type'))->toBe('application/problem+json; charset=utf-8');
});
