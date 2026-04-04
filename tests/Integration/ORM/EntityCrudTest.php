<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\ORM\Connection;
use Tests\Fixtures\Entities\TestUser;
use Tests\Fixtures\Entities\TestRole;
use Tests\Fixtures\Entities\TestOrder;

beforeEach(function () {
    Connection::reset();
    Connection::configure(['driver' => 'sqlite', 'database' => ':memory:']);
    $db = Connection::getInstance();
    $db->exec(file_get_contents(__DIR__ . '/../../Fixtures/migrations.sql'));
    TestUser::clearMetadataCache();
    TestRole::clearMetadataCache();
    TestOrder::clearMetadataCache();
});

test('save inserts new record and returns with ID', function () {
    $user = new TestUser();
    $user->name = 'Carlos';
    $user->email = 'carlos@test.com';
    $user->password = 'secret123';
    $user->active = true;
    $user->save();

    expect($user->id)->toBeInt()
        ->and($user->id)->toBeGreaterThan(0);
});

test('save updates existing record', function () {
    $user = new TestUser();
    $user->name = 'Carlos';
    $user->email = 'carlos@test.com';
    $user->password = 'secret';
    $user->save();

    $user->name = 'Carlos Updated';
    $user->save();

    $found = TestUser::find($user->id);
    expect($found->name)->toBe('Carlos Updated');
});

test('find returns entity by ID', function () {
    $user = new TestUser();
    $user->name = 'Carlos';
    $user->email = 'carlos@test.com';
    $user->password = 'secret';
    $user->save();

    $found = TestUser::find($user->id);

    expect($found)->not->toBeNull()
        ->and($found->name)->toBe('Carlos')
        ->and($found->email)->toBe('carlos@test.com');
});

test('find returns null for non-existent ID', function () {
    expect(TestUser::find(999))->toBeNull();
});

test('all returns array of entities', function () {
    $user1 = new TestUser();
    $user1->name = 'User 1';
    $user1->email = 'user1@test.com';
    $user1->password = 'pass';
    $user1->save();

    $user2 = new TestUser();
    $user2->name = 'User 2';
    $user2->email = 'user2@test.com';
    $user2->password = 'pass';
    $user2->save();

    $all = TestUser::all();

    expect($all)->toHaveCount(2)
        ->and($all[0])->toBeInstanceOf(TestUser::class);
});

test('delete removes record', function () {
    $role = new TestRole();
    $role->name = 'Admin';
    $role->save();

    $role->delete();

    expect(TestRole::find($role->id))->toBeNull();
});

test('soft delete sets deleted_at instead of removing', function () {
    $user = new TestUser();
    $user->name = 'Carlos';
    $user->email = 'carlos@test.com';
    $user->password = 'secret';
    $user->save();

    $user->delete();

    // find() should not return soft-deleted records
    expect(TestUser::find($user->id))->toBeNull();

    // But the record still exists in DB
    $rows = Connection::getInstance()->query('SELECT * FROM users WHERE id = ?', [$user->id]);
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['deleted_at'])->not->toBeNull();
});

test('all excludes soft-deleted records', function () {
    $user1 = new TestUser();
    $user1->name = 'Active';
    $user1->email = 'active@test.com';
    $user1->password = 'pass';
    $user1->save();

    $user2 = new TestUser();
    $user2->name = 'Deleted';
    $user2->email = 'deleted@test.com';
    $user2->password = 'pass';
    $user2->save();
    $user2->delete();

    expect(TestUser::all())->toHaveCount(1);
});

test('hooks are called during save', function () {
    $user = new TestUser();
    $user->name = 'Carlos';
    $user->email = 'carlos@test.com';
    $user->password = 'secret';

    expect($user->wasHookCalled())->toBeFalse();

    $user->save();

    expect($user->wasHookCalled())->toBeTrue();
});

test('validation runs before save and blocks invalid data', function () {
    $user = new TestUser();
    $user->password = 'secret';
    // missing required name and email

    expect(fn() => $user->save())->toThrow(RuntimeException::class);
});

test('fill populates entity from array with snake_case keys', function () {
    $user = new TestUser();
    $user->fill([
        'name' => 'Carlos',
        'email' => 'carlos@test.com',
        'password' => 'secret',
        'role_id' => 5,
    ]);

    expect($user->name)->toBe('Carlos')
        ->and($user->email)->toBe('carlos@test.com')
        ->and($user->roleId)->toBe(5);
});
