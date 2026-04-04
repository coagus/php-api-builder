<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\OpenAPI\SchemaGenerator;
use Tests\Fixtures\Entities\TestUser;
use Tests\Fixtures\Entities\TestRole;
use Tests\Fixtures\Entities\TestOrder;

test('int type maps to integer', function () {
    $schema = SchemaGenerator::generate(TestRole::class);

    expect($schema['properties']['id']['type'])->toBe('integer');
});

test('string type maps to string', function () {
    $schema = SchemaGenerator::generate(TestRole::class);

    expect($schema['properties']['name']['type'])->toBe('string');
});

test('Required adds field to required array', function () {
    $schema = SchemaGenerator::generate(TestRole::class);

    expect($schema['required'])->toContain('name');
});

test('Hidden fields are excluded from schema', function () {
    $schema = SchemaGenerator::generate(TestUser::class);

    expect($schema['properties'])->not->toHaveKey('password');
});

test('MaxLength generates maxLength', function () {
    $schema = SchemaGenerator::generate(TestUser::class);

    expect($schema['properties']['name']['maxLength'])->toBe(100);
});

test('PrimaryKey generates readOnly', function () {
    $schema = SchemaGenerator::generate(TestUser::class);

    expect($schema['properties']['id']['readOnly'])->toBeTrue();
});

test('Email generates format email', function () {
    $schema = SchemaGenerator::generate(TestUser::class);

    expect($schema['properties']['email']['format'])->toBe('email');
});

test('float type maps to number with float format', function () {
    $schema = SchemaGenerator::generate(TestOrder::class);

    expect($schema['properties']['total']['type'])->toBe('number')
        ->and($schema['properties']['total']['format'])->toBe('float');
});

test('bool type maps to boolean', function () {
    $schema = SchemaGenerator::generate(TestUser::class);

    expect($schema['properties']['active']['type'])->toBe('boolean');
});

test('default value is included', function () {
    $schema = SchemaGenerator::generate(TestUser::class);

    expect($schema['properties']['active']['default'])->toBeFalse();
});
