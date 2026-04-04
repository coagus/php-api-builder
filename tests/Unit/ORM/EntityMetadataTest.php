<?php

declare(strict_types=1);

use Tests\Fixtures\Entities\TestUser;
use Tests\Fixtures\Entities\TestRole;
use Tests\Fixtures\Entities\TestOrder;

beforeEach(function () {
    TestUser::clearMetadataCache();
    TestRole::clearMetadataCache();
    TestOrder::clearMetadataCache();
});

test('reads Table attribute correctly', function () {
    expect(TestUser::getTableName())->toBe('users')
        ->and(TestRole::getTableName())->toBe('roles')
        ->and(TestOrder::getTableName())->toBe('orders');
});

test('reads PrimaryKey attribute correctly', function () {
    expect(TestUser::getPrimaryKeyField())->toBe('id')
        ->and(TestRole::getPrimaryKeyField())->toBe('id');
});

test('reads SoftDelete attribute', function () {
    expect(TestUser::hasSoftDelete())->toBeTrue()
        ->and(TestRole::hasSoftDelete())->toBeFalse()
        ->and(TestOrder::hasSoftDelete())->toBeFalse();
});

test('toArray excludes Hidden fields', function () {
    $user = new TestUser();
    $user->id = 1;
    $user->name = 'Carlos';
    $user->email = 'carlos@test.com';
    $user->password = 'secret123';
    $user->active = true;

    $array = $user->toArray();

    expect($array)->toHaveKey('name')
        ->and($array)->toHaveKey('email')
        ->and($array)->not->toHaveKey('password');
});

test('toArray converts camelCase to snake_case', function () {
    $user = new TestUser();
    $user->id = 1;
    $user->name = 'Carlos';
    $user->email = 'carlos@test.com';
    $user->password = 'secret';
    $user->roleId = 1;

    $array = $user->toArray();

    expect($array)->toHaveKey('role_id')
        ->and($array)->not->toHaveKey('roleId');
});
