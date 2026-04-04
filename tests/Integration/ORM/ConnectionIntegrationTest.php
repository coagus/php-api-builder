<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\ORM\Connection;

beforeEach(function () {
    Connection::reset();
    Connection::configure(['driver' => 'sqlite', 'database' => ':memory:']);
});

test('connects to SQLite in-memory and creates a table', function () {
    $db = Connection::getInstance();

    $db->exec('CREATE TABLE test_users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT NOT NULL)');

    $db->execute('INSERT INTO test_users (name, email) VALUES (?, ?)', ['Carlos', 'carlos@test.com']);

    $rows = $db->query('SELECT * FROM test_users');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['name'])->toBe('Carlos')
        ->and($rows[0]['email'])->toBe('carlos@test.com');
});

test('insert returns last insert ID', function () {
    $db = Connection::getInstance();

    $db->exec('CREATE TABLE test_items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');

    $db->execute('INSERT INTO test_items (name) VALUES (?)', ['Item 1']);
    $id1 = $db->lastInsertId();

    $db->execute('INSERT INTO test_items (name) VALUES (?)', ['Item 2']);
    $id2 = $db->lastInsertId();

    expect((int)$id1)->toBe(1)
        ->and((int)$id2)->toBe(2);
});

test('transaction commits on success', function () {
    $db = Connection::getInstance();

    $db->exec('CREATE TABLE test_tx (id INTEGER PRIMARY KEY AUTOINCREMENT, value TEXT)');

    $result = $db->transaction(function (Connection $db) {
        $db->execute('INSERT INTO test_tx (value) VALUES (?)', ['committed']);
        return 'done';
    });

    expect($result)->toBe('done');

    $rows = $db->query('SELECT * FROM test_tx');
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['value'])->toBe('committed');
});

test('transaction rolls back on exception', function () {
    $db = Connection::getInstance();

    $db->exec('CREATE TABLE test_rb (id INTEGER PRIMARY KEY AUTOINCREMENT, value TEXT)');

    try {
        $db->transaction(function (Connection $db) {
            $db->execute('INSERT INTO test_rb (value) VALUES (?)', ['should_rollback']);
            throw new RuntimeException('Test rollback');
        });
    } catch (RuntimeException) {
        // expected
    }

    $rows = $db->query('SELECT * FROM test_rb');
    expect($rows)->toHaveCount(0);
});

test('execute returns affected row count', function () {
    $db = Connection::getInstance();

    $db->exec('CREATE TABLE test_count (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');

    $db->execute('INSERT INTO test_count (name) VALUES (?)', ['one']);
    $db->execute('INSERT INTO test_count (name) VALUES (?)', ['two']);
    $db->execute('INSERT INTO test_count (name) VALUES (?)', ['three']);

    $affected = $db->execute('DELETE FROM test_count WHERE name IN (?, ?)', ['one', 'two']);

    expect($affected)->toBe(2);
});
