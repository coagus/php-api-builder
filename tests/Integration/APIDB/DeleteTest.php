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

    $db->execute("INSERT INTO roles (name) VALUES (?)", ['Admin']);
});

test('DELETE returns 204', function () {
    $handler = new TestRoleApidb();
    $handler->setRequest(new Request('DELETE', '/api/v1/roles/1'));
    $handler->setResourceId('1');
    $handler->delete();
    $response = $handler->getResponse();

    expect($response->getStatusCode())->toBe(204)
        ->and($response->getBody())->toBeNull();

    // Verify deleted
    expect(TestRole::find(1))->toBeNull();
});

test('DELETE with SoftDelete sets deleted_at', function () {
    Connection::getInstance()->execute(
        "INSERT INTO users (name, email, password, active, role_id) VALUES (?, ?, ?, ?, ?)",
        ['Alice', 'alice@test.com', 'pass', 1, 1]
    );

    $handler = new TestUserApidb();
    $handler->setRequest(new Request('DELETE', '/api/v1/users/1'));
    $handler->setResourceId('1');
    $handler->delete();
    $response = $handler->getResponse();

    expect($response->getStatusCode())->toBe(204);

    // find() should return null (soft deleted)
    expect(TestUser::find(1))->toBeNull();

    // But record still exists in DB
    $rows = Connection::getInstance()->query('SELECT * FROM users WHERE id = ?', [1]);
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['deleted_at'])->not->toBeNull();
});

test('GET after soft delete does not return the record', function () {
    Connection::getInstance()->execute(
        "INSERT INTO users (name, email, password, active, role_id) VALUES (?, ?, ?, ?, ?)",
        ['Alice', 'alice@test.com', 'pass', 1, 1]
    );

    // Delete user
    $handler = new TestUserApidb();
    $handler->setRequest(new Request('DELETE', '/api/v1/users/1'));
    $handler->setResourceId('1');
    $handler->delete();

    // Try to GET it
    $handler2 = new TestUserApidb();
    $handler2->setRequest(new Request('GET', '/api/v1/users/1'));
    $handler2->setResourceId('1');
    $handler2->get();

    expect($handler2->getResponse()->getStatusCode())->toBe(404);
});

test('DELETE non-existent ID returns 404', function () {
    $handler = new TestRoleApidb();
    $handler->setRequest(new Request('DELETE', '/api/v1/roles/999'));
    $handler->setResourceId('999');
    $handler->delete();

    expect($handler->getResponse()->getStatusCode())->toBe(404);
});
