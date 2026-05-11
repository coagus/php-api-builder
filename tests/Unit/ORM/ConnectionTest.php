<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\ORM\Connection;

beforeEach(function () {
    Connection::reset();
});

test('Connection is a singleton', function () {
    Connection::configure(['driver' => 'sqlite', 'database' => ':memory:']);

    $instance1 = Connection::getInstance();
    $instance2 = Connection::getInstance();

    expect($instance1)->toBe($instance2);
});

test('Connection can be configured with array of settings', function () {
    Connection::configure(['driver' => 'sqlite', 'database' => ':memory:']);

    $connection = Connection::getInstance();

    expect($connection)->toBeInstanceOf(Connection::class);
    expect($connection->getPdo())->toBeInstanceOf(PDO::class);
});

test('Connection throws exception if not configured', function () {
    Connection::getInstance();
})->throws(RuntimeException::class, 'Connection not configured');

test('Connection throws exception for unsupported driver', function () {
    Connection::configure(['driver' => 'oracle', 'database' => 'test']);
    Connection::getInstance();
})->throws(RuntimeException::class, 'Unsupported database driver: oracle');

test('Connection::configure expands a dsn URI into individual fields', function () {
    Connection::configure(['dsn' => 'postgresql://alice:s3cret@db.example.com:6543/postgres']);

    $config = (function () { return self::$config; })->bindTo(null, Connection::class)();

    expect($config)->toMatchArray([
        'driver'   => 'pgsql',
        'host'     => 'db.example.com',
        'port'     => 6543,
        'database' => 'postgres',
        'username' => 'alice',
        'password' => 's3cret',
    ]);
    // The original `dsn` key is removed after normalization.
    expect($config)->not->toHaveKey('dsn');
});

test('Connection::configure lets caller-provided keys override URI-parsed ones', function () {
    Connection::configure([
        'dsn'      => 'postgresql://alice:s3cret@db.example.com:6543/postgres',
        'database' => 'override_db',
    ]);

    $config = (function () { return self::$config; })->bindTo(null, Connection::class)();

    expect($config['database'])->toBe('override_db');
    expect($config['host'])->toBe('db.example.com');
});

test('Connection::configure leaves config untouched when dsn key is absent', function () {
    Connection::configure(['driver' => 'sqlite', 'database' => ':memory:']);

    $config = (function () { return self::$config; })->bindTo(null, Connection::class)();

    expect($config)->toBe(['driver' => 'sqlite', 'database' => ':memory:']);
});

test('Connection::configure leaves a non-URI dsn value alone (passthrough)', function () {
    // A PDO-native DSN string is not a URI — it should pass through unchanged.
    Connection::configure(['driver' => 'pgsql', 'dsn' => 'pgsql:host=h;dbname=d', 'database' => 'd']);

    $config = (function () { return self::$config; })->bindTo(null, Connection::class)();

    expect($config['driver'])->toBe('pgsql');
    expect($config['dsn'])->toBe('pgsql:host=h;dbname=d');
});
