<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\ORM\Connection;
use Tests\Fixtures\Entities\TestUser;
use Tests\Fixtures\Entities\TestRole;
use Tests\Fixtures\Entities\TestOrder;
use Tests\Fixtures\Entities\TestTag;

beforeEach(function () {
    Connection::reset();
    Connection::configure(['driver' => 'sqlite', 'database' => ':memory:']);
    $db = Connection::getInstance();
    $db->exec(file_get_contents(__DIR__ . '/../../Fixtures/migrations.sql'));
    TestUser::clearMetadataCache();
    TestRole::clearMetadataCache();
    TestOrder::clearMetadataCache();
    TestTag::clearMetadataCache();

    // Seed roles
    $db->execute("INSERT INTO roles (name) VALUES (?)", ['Admin']);
    $db->execute("INSERT INTO roles (name) VALUES (?)", ['User']);

    // Seed users
    $db->execute("INSERT INTO users (name, email, password, active, role_id) VALUES (?, ?, ?, ?, ?)", ['Alice', 'alice@test.com', 'pass', 1, 1]);
    $db->execute("INSERT INTO users (name, email, password, active, role_id) VALUES (?, ?, ?, ?, ?)", ['Bob', 'bob@test.com', 'pass', 1, 2]);

    // Seed orders
    $db->execute("INSERT INTO orders (user_id, total, status) VALUES (?, ?, ?)", [1, 100.50, 'completed']);
    $db->execute("INSERT INTO orders (user_id, total, status) VALUES (?, ?, ?)", [1, 200.00, 'pending']);
    $db->execute("INSERT INTO orders (user_id, total, status) VALUES (?, ?, ?)", [2, 50.00, 'completed']);

    // Seed tags
    $db->execute("INSERT INTO tags (name) VALUES (?)", ['php']);
    $db->execute("INSERT INTO tags (name) VALUES (?)", ['api']);
    $db->execute("INSERT INTO tags (name) VALUES (?)", ['rest']);

    // Seed pivot
    $db->execute("INSERT INTO user_tags (user_id, tag_id) VALUES (?, ?)", [1, 1]);
    $db->execute("INSERT INTO user_tags (user_id, tag_id) VALUES (?, ?)", [1, 2]);
    $db->execute("INSERT INTO user_tags (user_id, tag_id) VALUES (?, ?)", [2, 2]);
    $db->execute("INSERT INTO user_tags (user_id, tag_id) VALUES (?, ?)", [2, 3]);
});

test('BelongsTo: lazy loading role on user', function () {
    $user = TestUser::find(1);
    $user->loadRelation('role');

    expect($user->role)->not->toBeNull()
        ->and($user->role)->toBeInstanceOf(TestRole::class)
        ->and($user->role->name)->toBe('Admin');
});

test('HasMany: lazy loading orders on user', function () {
    $user = TestUser::find(1);
    $user->loadRelation('orders');

    expect($user->orders)->toHaveCount(2)
        ->and($user->orders[0])->toBeInstanceOf(TestOrder::class);
});

test('BelongsToMany: lazy loading tags on user', function () {
    $user = TestUser::find(1);
    $user->loadRelation('tags');

    expect($user->tags)->toHaveCount(2)
        ->and($user->tags[0])->toBeInstanceOf(TestTag::class);
});

test('eager loading with() BelongsTo avoids N+1', function () {
    $users = TestUser::query()->with('role')->get();

    expect($users)->toHaveCount(2)
        ->and($users[0]->role)->toBeInstanceOf(TestRole::class)
        ->and($users[0]->role->name)->toBe('Admin')
        ->and($users[1]->role)->toBeInstanceOf(TestRole::class)
        ->and($users[1]->role->name)->toBe('User');
});

test('eager loading with() HasMany', function () {
    $users = TestUser::query()->with('orders')->get();

    expect($users[0]->orders)->toHaveCount(2)
        ->and($users[1]->orders)->toHaveCount(1);
});

test('eager loading with() BelongsToMany', function () {
    $users = TestUser::query()->with('tags')->get();

    expect($users[0]->tags)->toHaveCount(2)
        ->and($users[1]->tags)->toHaveCount(2);
});

test('eager loading multiple relations at once', function () {
    $users = TestUser::query()->with('role', 'orders', 'tags')->get();

    expect($users[0]->role->name)->toBe('Admin')
        ->and($users[0]->orders)->toHaveCount(2)
        ->and($users[0]->tags)->toHaveCount(2);
});
