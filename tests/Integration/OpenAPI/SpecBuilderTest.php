<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\OpenAPI\SpecBuilder;
use Tests\Fixtures\Entities\TestUser;
use Tests\Fixtures\Entities\TestRole;

test('generated spec is valid JSON', function () {
    $builder = new SpecBuilder('Test API', '1.0.0');
    $builder->addEntity(TestRole::class, 'roles');
    $json = $builder->toJson();

    $decoded = json_decode($json, true);
    expect(json_last_error())->toBe(JSON_ERROR_NONE)
        ->and($decoded['openapi'])->toBe('3.1.0');
});

test('CRUD paths are present', function () {
    $builder = new SpecBuilder('Test API', '1.0.0');
    $builder->addEntity(TestRole::class, 'roles');
    $spec = $builder->build();

    expect($spec['paths'])->toHaveKey('/api/v1/roles')
        ->and($spec['paths'])->toHaveKey('/api/v1/roles/{id}')
        ->and($spec['paths']['/api/v1/roles'])->toHaveKey('get')
        ->and($spec['paths']['/api/v1/roles'])->toHaveKey('post')
        ->and($spec['paths']['/api/v1/roles/{id}'])->toHaveKey('get')
        ->and($spec['paths']['/api/v1/roles/{id}'])->toHaveKey('put')
        ->and($spec['paths']['/api/v1/roles/{id}'])->toHaveKey('patch')
        ->and($spec['paths']['/api/v1/roles/{id}'])->toHaveKey('delete');
});

test('schemas are generated for entities', function () {
    $builder = new SpecBuilder('Test API', '1.0.0');
    $builder->addEntity(TestRole::class, 'roles');
    $spec = $builder->build();

    expect($spec['components']['schemas'])->toHaveKey('TestRole')
        ->and($spec['components']['schemas'])->toHaveKey('TestRoleCreate')
        ->and($spec['components']['schemas'])->toHaveKey('PaginationMeta');
});

test('security schemes include bearerAuth', function () {
    $builder = new SpecBuilder('Test API', '1.0.0');
    $builder->addEntity(TestRole::class, 'roles');
    $spec = $builder->build();

    expect($spec['components']['securitySchemes']['bearerAuth']['type'])->toBe('http')
        ->and($spec['components']['securitySchemes']['bearerAuth']['scheme'])->toBe('bearer');
});

test('multiple entities generate separate paths', function () {
    $builder = new SpecBuilder('Test API', '1.0.0');
    $builder->addEntity(TestRole::class, 'roles');
    $builder->addEntity(TestUser::class, 'users');
    $spec = $builder->build();

    expect($spec['paths'])->toHaveKey('/api/v1/roles')
        ->and($spec['paths'])->toHaveKey('/api/v1/users');
});
