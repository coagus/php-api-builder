<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\Http\Request;
use Coagus\PhpApiBuilder\ORM\Connection;
use Tests\Fixtures\Entities\TestUser;
use Tests\Fixtures\Entities\TestRole;
use Tests\Fixtures\TestRoleApidb;

beforeEach(function () {
    Connection::reset();
    Connection::configure(['driver' => 'sqlite', 'database' => ':memory:']);
    $db = Connection::getInstance();
    $db->exec(file_get_contents(__DIR__ . '/../../Fixtures/migrations.sql'));
    TestUser::clearMetadataCache();
    TestRole::clearMetadataCache();

    $db->execute("INSERT INTO roles (name) VALUES (?)", ['Admin']);
    $db->execute("INSERT INTO roles (name) VALUES (?)", ['User']);
});

test('PUT replaces all fields', function () {
    $handler = new TestRoleApidb();
    $handler->setRequest(new Request('PUT', '/api/v1/roles/1', [], '{"name":"SuperAdmin"}'));
    $handler->setResourceId('1');
    $handler->put();
    $response = $handler->getResponse();
    $body = $response->getBody();

    expect($response->getStatusCode())->toBe(200)
        ->and($body['data']['name'])->toBe('SuperAdmin');

    // Verify in DB
    $role = TestRole::find(1);
    expect($role->name)->toBe('SuperAdmin');
});

test('PATCH updates only sent fields', function () {
    $handler = new TestRoleApidb();
    $handler->setRequest(new Request('PATCH', '/api/v1/roles/2', [], '{"name":"Editor"}'));
    $handler->setResourceId('2');
    $handler->patch();
    $response = $handler->getResponse();
    $body = $response->getBody();

    expect($response->getStatusCode())->toBe(200)
        ->and($body['data']['name'])->toBe('Editor');
});

test('PUT non-existent ID returns 404', function () {
    $handler = new TestRoleApidb();
    $handler->setRequest(new Request('PUT', '/api/v1/roles/999', [], '{"name":"Ghost"}'));
    $handler->setResourceId('999');
    $handler->put();
    $response = $handler->getResponse();

    expect($response->getStatusCode())->toBe(404);
});

test('PATCH non-existent ID returns 404', function () {
    $handler = new TestRoleApidb();
    $handler->setRequest(new Request('PATCH', '/api/v1/roles/999', [], '{"name":"Ghost"}'));
    $handler->setResourceId('999');
    $handler->patch();
    $response = $handler->getResponse();

    expect($response->getStatusCode())->toBe(404);
});
