<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\Http\Request;
use Coagus\PhpApiBuilder\ORM\Connection;
use Tests\Fixtures\Entities\TestUser;
use Tests\Fixtures\Entities\TestRole;
use Tests\Fixtures\TestRoleApidb;
use Tests\Fixtures\TestUserApidb;

beforeEach(function () {
    Connection::reset();
    Connection::configure(['driver' => 'sqlite', 'database' => ':memory:']);
    $db = Connection::getInstance();
    $db->exec(file_get_contents(__DIR__ . '/../../Fixtures/migrations.sql'));
    TestUser::clearMetadataCache();
    TestRole::clearMetadataCache();
});

test('POST valid data returns 201 with data', function () {
    $handler = new TestRoleApidb();
    $handler->setRequest(new Request('POST', '/api/v1/roles', [], '{"name":"Editor"}'));
    $handler->post();
    $response = $handler->getResponse();
    $body = $response->getBody();

    expect($response->getStatusCode())->toBe(201)
        ->and($body['data']['name'])->toBe('Editor')
        ->and($body['data']['id'])->toBeGreaterThan(0);
});

test('POST invalid data (missing required) returns 422 with errors', function () {
    $handler = new TestUserApidb();
    $handler->setRequest(new Request('POST', '/api/v1/users', [], '{"password":"secret"}'));
    $handler->post();
    $response = $handler->getResponse();
    $body = $response->getBody();

    expect($response->getStatusCode())->toBe(422)
        ->and($body['title'])->toBe('Validation Error')
        ->and($body['errors'])->toHaveKey('name')
        ->and($body['errors'])->toHaveKey('email');
});

test('POST with invalid email returns 422', function () {
    $handler = new TestUserApidb();
    $handler->setRequest(new Request('POST', '/api/v1/users', [], '{"name":"Test","email":"not-email","password":"pass"}'));
    $handler->post();
    $response = $handler->getResponse();

    expect($response->getStatusCode())->toBe(422)
        ->and($response->getBody()['errors'])->toHaveKey('email');
});
