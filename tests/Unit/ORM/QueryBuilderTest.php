<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\ORM\QueryBuilder;
use Tests\Fixtures\Entities\TestRole;

test('where generates correct SQL with bind', function () {
    $qb = new QueryBuilder(TestRole::class);
    $sql = $qb->where('active', true)->toSql();
    $bindings = $qb->getBindings();

    expect($sql)->toContain('WHERE active = ?')
        ->and($bindings)->toBe([true]);
});

test('where with operator generates correct SQL', function () {
    $qb = new QueryBuilder(TestRole::class);
    $sql = $qb->where('price', '>=', 100)->toSql();

    expect($sql)->toContain('WHERE price >= ?')
        ->and($qb->getBindings())->toBe([100]);
});

test('whereIn generates correct SQL', function () {
    $qb = new QueryBuilder(TestRole::class);
    $sql = $qb->whereIn('status', ['active', 'pending'])->toSql();

    expect($sql)->toContain('WHERE status IN (?, ?)')
        ->and($qb->getBindings())->toBe(['active', 'pending']);
});

test('orderBy generates correct SQL', function () {
    $qb = new QueryBuilder(TestRole::class);
    $sql = $qb->orderBy('createdAt', 'desc')->toSql();

    expect($sql)->toContain('ORDER BY created_at DESC');
});

test('select generates correct SQL', function () {
    $qb = new QueryBuilder(TestRole::class);
    $sql = $qb->select('id', 'name')->toSql();

    expect($sql)->toContain('SELECT id, name FROM');
});

test('limit and offset generate correct SQL', function () {
    $qb = new QueryBuilder(TestRole::class);
    $sql = $qb->limit(10)->offset(20)->toSql();

    expect($sql)->toContain('LIMIT 10 OFFSET 20');
});

test('chaining multiple conditions generates correct SQL', function () {
    $qb = new QueryBuilder(TestRole::class);
    $sql = $qb
        ->where('active', true)
        ->where('roleId', '>=', 2)
        ->orderBy('name')
        ->limit(10)
        ->toSql();

    expect($sql)->toContain('WHERE active = ? AND role_id >= ?')
        ->and($sql)->toContain('ORDER BY name ASC')
        ->and($sql)->toContain('LIMIT 10')
        ->and($qb->getBindings())->toBe([true, 2]);
});

test('toSql never contains direct values, only placeholders', function () {
    $qb = new QueryBuilder(TestRole::class);
    $sql = $qb
        ->where('name', 'Admin')
        ->where('level', '>', 5)
        ->whereIn('status', ['active', 'pending'])
        ->toSql();

    expect($sql)->not->toContain("'Admin'")
        ->and($sql)->not->toContain("'active'")
        ->and($sql)->not->toContain("'pending'")
        ->and($sql)->not->toContain(' 5');

    // But it should contain ? placeholders
    expect(substr_count($sql, '?'))->toBe(4);
});

test('whereBetween generates correct SQL', function () {
    $qb = new QueryBuilder(TestRole::class);
    $sql = $qb->whereBetween('createdAt', ['2026-01-01', '2026-12-31'])->toSql();

    expect($sql)->toContain('created_at BETWEEN ? AND ?')
        ->and($qb->getBindings())->toBe(['2026-01-01', '2026-12-31']);
});

test('whereNull generates correct SQL', function () {
    $qb = new QueryBuilder(TestRole::class);
    $sql = $qb->whereNull('deletedAt')->toSql();

    expect($sql)->toContain('deleted_at IS NULL');
});

test('whereNotNull generates correct SQL', function () {
    $qb = new QueryBuilder(TestRole::class);
    $sql = $qb->whereNotNull('email')->toSql();

    expect($sql)->toContain('email IS NOT NULL');
});

test('orWhere generates correct SQL', function () {
    $qb = new QueryBuilder(TestRole::class);
    $sql = $qb->where('active', true)->orWhere('name', 'Admin')->toSql();

    expect($sql)->toContain('WHERE active = ? OR name = ?');
});
