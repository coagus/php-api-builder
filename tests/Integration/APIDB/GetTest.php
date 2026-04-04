<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\Http\Request;
use Coagus\PhpApiBuilder\ORM\Connection;
use Tests\Fixtures\Entities\TestUser;
use Tests\Fixtures\Entities\TestRole;
use Tests\Fixtures\TestUserApidb;
use Tests\Fixtures\TestRoleApidb;

beforeEach(function () {
    Connection::reset();
    Connection::configure(['driver' => 'sqlite', 'database' => ':memory:']);
    $db = Connection::getInstance();
    $db->exec(file_get_contents(__DIR__ . '/../../Fixtures/migrations.sql'));
    TestUser::clearMetadataCache();
    TestRole::clearMetadataCache();

    // Seed data
    $db->execute("INSERT INTO roles (name) VALUES (?)", ['Admin']);
    $db->execute("INSERT INTO roles (name) VALUES (?)", ['User']);

    $db->execute("INSERT INTO users (name, email, password, active, role_id) VALUES (?, ?, ?, ?, ?)", ['Alice', 'alice@test.com', 'pass', 1, 1]);
    $db->execute("INSERT INTO users (name, email, password, active, role_id) VALUES (?, ?, ?, ?, ?)", ['Bob', 'bob@test.com', 'pass', 1, 2]);
    $db->execute("INSERT INTO users (name, email, password, active, role_id) VALUES (?, ?, ?, ?, ?)", ['Charlie', 'charlie@test.com', 'pass', 0, 2]);
    $db->execute("INSERT INTO users (name, email, password, active, role_id) VALUES (?, ?, ?, ?, ?)", ['Diana', 'diana@test.com', 'pass', 1, 1]);
    $db->execute("INSERT INTO users (name, email, password, active, role_id) VALUES (?, ?, ?, ?, ?)", ['Eve', 'eve@test.com', 'pass', 1, 2]);
});

function createApidb(string $class, string $method, string $uri, ?string $id = null, ?string $body = null): object
{
    $handler = new $class();
    $request = new Request($method, $uri, [], $body ?? '');
    $handler->setRequest($request);
    $handler->setResourceId($id);
    return $handler;
}

test('GET list returns data, meta format', function () {
    $handler = createApidb(TestUserApidb::class, 'GET', '/api/v1/users');
    $handler->get();
    $response = $handler->getResponse();
    $body = $response->getBody();

    expect($response->getStatusCode())->toBe(200)
        ->and($body)->toHaveKey('data')
        ->and($body)->toHaveKey('meta')
        ->and($body['meta'])->toHaveKey('current_page')
        ->and($body['meta'])->toHaveKey('per_page')
        ->and($body['meta'])->toHaveKey('total')
        ->and($body['meta'])->toHaveKey('total_pages');
});

test('GET list with pagination', function () {
    $handler = createApidb(TestUserApidb::class, 'GET', '/api/v1/users?page=2&per_page=2');
    $handler->get();
    $body = $handler->getResponse()->getBody();

    expect($body['data'])->toHaveCount(2)
        ->and($body['meta']['current_page'])->toBe(2)
        ->and($body['meta']['per_page'])->toBe(2)
        ->and($body['meta']['total'])->toBe(5)
        ->and($body['meta']['total_pages'])->toBe(3);
});

test('GET list with sorting', function () {
    $handler = createApidb(TestUserApidb::class, 'GET', '/api/v1/users?sort=-name');
    $handler->get();
    $body = $handler->getResponse()->getBody();

    expect($body['data'][0]['name'])->toBe('Eve');
});

test('GET list with filters', function () {
    $handler = createApidb(TestUserApidb::class, 'GET', '/api/v1/users?filter[active]=true');
    $handler->get();
    $body = $handler->getResponse()->getBody();

    expect($body['meta']['total'])->toBe(4); // All active users
});

test('GET list with sparse fields', function () {
    $handler = createApidb(TestUserApidb::class, 'GET', '/api/v1/users?fields=id,name');
    $handler->get();
    $body = $handler->getResponse()->getBody();

    expect($body['data'][0])->toHaveKey('id')
        ->and($body['data'][0])->toHaveKey('name');
});

test('GET by ID returns single entity', function () {
    $handler = createApidb(TestUserApidb::class, 'GET', '/api/v1/users/1', '1');
    $handler->get();
    $response = $handler->getResponse();
    $body = $response->getBody();

    expect($response->getStatusCode())->toBe(200)
        ->and($body['data']['name'])->toBe('Alice')
        ->and($body['data']['email'])->toBe('alice@test.com');
});

test('GET by non-existent ID returns 404', function () {
    $handler = createApidb(TestUserApidb::class, 'GET', '/api/v1/users/999', '999');
    $handler->get();
    $response = $handler->getResponse();

    expect($response->getStatusCode())->toBe(404)
        ->and($response->getBody()['title'])->toBe('Resource not found');
});
