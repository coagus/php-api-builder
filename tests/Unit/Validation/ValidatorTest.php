<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\Validation\Validator;
use Tests\Fixtures\Entities\TestUser;
use Tests\Fixtures\Entities\TestRole;

test('Required rejects null (uninitialized) field', function () {
    $user = new TestUser();
    $user->password = 'pass';
    $user->active = true;
    // name and email not set

    $errors = Validator::validate($user);

    expect($errors)->not->toBeNull()
        ->and($errors)->toHaveKey('name')
        ->and($errors)->toHaveKey('email');
});

test('Required rejects empty string', function () {
    $user = new TestUser();
    $user->name = '';
    $user->email = 'test@test.com';
    $user->password = 'pass';

    $errors = Validator::validate($user);

    expect($errors)->not->toBeNull()
        ->and($errors)->toHaveKey('name');
});

test('Email validates format', function () {
    $user = new TestUser();
    $user->name = 'Carlos';
    $user->email = 'not-an-email';
    $user->password = 'pass';

    $errors = Validator::validate($user);

    expect($errors)->not->toBeNull()
        ->and($errors)->toHaveKey('email');
});

test('Email accepts valid email', function () {
    $user = new TestUser();
    $user->name = 'Carlos';
    $user->email = 'carlos@test.com';
    $user->password = 'pass';

    $errors = Validator::validate($user);

    expect($errors)->toBeNull();
});

test('MaxLength rejects strings that are too long', function () {
    $user = new TestUser();
    $user->name = str_repeat('a', 101);
    $user->email = 'test@test.com';
    $user->password = 'pass';

    $errors = Validator::validate($user);

    expect($errors)->not->toBeNull()
        ->and($errors)->toHaveKey('name');
});

test('combined attributes all validate', function () {
    $user = new TestUser();
    $user->name = str_repeat('a', 101); // too long
    $user->email = 'invalid';           // bad email
    $user->password = 'pass';

    $errors = Validator::validate($user);

    expect($errors)->toHaveKey('name')
        ->and($errors)->toHaveKey('email');
});

test('entity without validation attributes passes', function () {
    $role = new TestRole();
    $role->name = 'Admin';

    $errors = Validator::validate($role);

    expect($errors)->toBeNull();
});
