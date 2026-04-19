<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\ORM\Connection;
use Tests\Fixtures\Entities\TestUser;
use Tests\Fixtures\Entities\TestRole;

beforeEach(function () {
    Connection::reset();
    Connection::configure(['driver' => 'sqlite', 'database' => ':memory:']);
    $db = Connection::getInstance();
    $db->exec(file_get_contents(__DIR__ . '/../../Fixtures/migrations.sql'));
    TestUser::clearMetadataCache();
    TestRole::clearMetadataCache();

    // Seed test data
    $db->execute("INSERT INTO roles (name) VALUES (?)", ['Admin']);
    $db->execute("INSERT INTO roles (name) VALUES (?)", ['User']);

    $db->execute("INSERT INTO users (name, email, password, active, role_id) VALUES (?, ?, ?, ?, ?)", ['Alice', 'alice@test.com', 'pass', 1, 1]);
    $db->execute("INSERT INTO users (name, email, password, active, role_id) VALUES (?, ?, ?, ?, ?)", ['Bob', 'bob@test.com', 'pass', 1, 2]);
    $db->execute("INSERT INTO users (name, email, password, active, role_id) VALUES (?, ?, ?, ?, ?)", ['Charlie', 'charlie@test.com', 'pass', 0, 2]);
    $db->execute("INSERT INTO users (name, email, password, active, role_id) VALUES (?, ?, ?, ?, ?)", ['Deleted', 'deleted@test.com', 'pass', 1, 1]);
    $db->execute("UPDATE users SET deleted_at = datetime('now') WHERE name = ?", ['Deleted']);
});

test('find retrieves entity by ID', function () {
    $user = TestUser::find(1);
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Alice');
});

test('all retrieves all non-deleted entities', function () {
    $users = TestUser::all();
    expect($users)->toHaveCount(3); // Deleted user excluded
});

test('where filters results', function () {
    $users = TestUser::query()->where('active', 1)->get();
    expect($users)->toHaveCount(2); // Alice and Bob (not Deleted, not Charlie)
});

test('orderBy sorts results', function () {
    $users = TestUser::query()->orderBy('name', 'desc')->get();
    expect($users[0]->name)->toBe('Charlie')
        ->and($users[1]->name)->toBe('Bob')
        ->and($users[2]->name)->toBe('Alice');
});

test('limit restricts results', function () {
    $users = TestUser::query()->limit(2)->get();
    expect($users)->toHaveCount(2);
});

test('first returns single entity', function () {
    $user = TestUser::query()->where('name', 'Bob')->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Bob');
});

test('first returns null when no match', function () {
    $user = TestUser::query()->where('name', 'NonExistent')->first();
    expect($user)->toBeNull();
});

test('count returns correct number', function () {
    $count = TestUser::query()->where('active', 1)->count();
    expect($count)->toBe(2);
});

test('paginate returns data and meta', function () {
    $result = TestUser::query()->paginate(page: 1, perPage: 2);

    expect($result)->toHaveKey('data')
        ->and($result)->toHaveKey('meta')
        ->and($result['data'])->toHaveCount(2)
        ->and($result['meta']['currentPage'])->toBe(1)
        ->and($result['meta']['perPage'])->toBe(2)
        ->and($result['meta']['total'])->toBe(3)
        ->and($result['meta']['totalPages'])->toBe(2);
});

test('paginate second page returns remaining items', function () {
    $result = TestUser::query()->paginate(page: 2, perPage: 2);

    expect($result['data'])->toHaveCount(1)
        ->and($result['meta']['currentPage'])->toBe(2);
});

test('soft deleted records are excluded from query builder', function () {
    $all = TestUser::query()->get();
    expect($all)->toHaveCount(3); // Deleted user excluded
});

test('raw SQL via Connection works (Level 5)', function () {
    $results = Connection::getInstance()->query(
        "SELECT name FROM users WHERE active = ? AND deleted_at IS NULL ORDER BY name",
        [1]
    );

    expect($results)->toHaveCount(2)
        ->and($results[0]['name'])->toBe('Alice')
        ->and($results[1]['name'])->toBe('Bob');
});

test('select specific fields works', function () {
    $users = TestUser::query()->select('id', 'name')->get();

    expect($users[0]->name)->toBe('Alice');
});

test('whereIn filters correctly', function () {
    $users = TestUser::query()->whereIn('name', ['Alice', 'Charlie'])->get();
    expect($users)->toHaveCount(2);
});

test('orWhere on an empty chain produces valid SQL and returns matches', function () {
    $users = TestUser::query()->orWhere('name', 'Alice')->get();

    expect($users)->toHaveCount(1)
        ->and($users[0]->name)->toBe('Alice');
});

test('soft-delete guard is NOT bypassed when orWhere is used', function () {
    // Without the fix, the clause "deleted_at IS NULL OR name = 'Deleted'"
    // would include the soft-deleted row. The correct SQL wraps the user
    // predicate in parentheses AND-joined with the guard.
    $users = TestUser::query()
        ->where('name', 'Alice')
        ->orWhere('name', 'Deleted')
        ->get();

    $names = array_map(fn($u) => $u->name, $users);

    expect($names)->toContain('Alice')
        ->and($names)->not->toContain('Deleted');
});

test('buildWhereSql parenthesizes the user predicate when soft-delete is active', function () {
    $qb = TestUser::query()->where('name', 'Alice')->orWhere('name', 'Bob');
    $sql = $qb->toSql();

    expect($sql)->toContain('WHERE deleted_at IS NULL AND (')
        ->and($sql)->toContain('name = ? OR name = ?');
});
