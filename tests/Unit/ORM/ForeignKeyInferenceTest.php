<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\Helpers\Utils;

test('appends _id when the property name has no suffix', function () {
    expect(Utils::foreignKeyColumn('user'))->toBe('user_id')
        ->and(Utils::foreignKeyColumn('category'))->toBe('category_id');
});

test('does not double the _id suffix when the property is camelCase and ends in Id', function () {
    expect(Utils::foreignKeyColumn('userId'))->toBe('user_id')
        ->and(Utils::foreignKeyColumn('categoryId'))->toBe('category_id')
        ->and(Utils::foreignKeyColumn('parentCategoryId'))->toBe('parent_category_id');
});

test('is idempotent on snake_case input already ending in _id', function () {
    expect(Utils::foreignKeyColumn('user_id'))->toBe('user_id')
        ->and(Utils::foreignKeyColumn('category_id'))->toBe('category_id');
});

test('never produces double suffixes regardless of input shape', function () {
    $inputs = ['user', 'userId', 'user_id', 'parentCategoryId', 'parent_category_id'];
    foreach ($inputs as $input) {
        expect(Utils::foreignKeyColumn($input))->not->toEndWith('_id_id');
    }
});
