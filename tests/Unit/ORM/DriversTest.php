<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\ORM\Drivers\DriverInterface;
use Coagus\PhpApiBuilder\ORM\Drivers\MySqlDriver;
use Coagus\PhpApiBuilder\ORM\Drivers\PostgresDriver;
use Coagus\PhpApiBuilder\ORM\Drivers\SqliteDriver;

test('MySqlDriver implements DriverInterface', function () {
    expect(new MySqlDriver())->toBeInstanceOf(DriverInterface::class);
});

test('PostgresDriver implements DriverInterface', function () {
    expect(new PostgresDriver())->toBeInstanceOf(DriverInterface::class);
});

test('SqliteDriver implements DriverInterface', function () {
    expect(new SqliteDriver())->toBeInstanceOf(DriverInterface::class);
});

test('MySqlDriver quotes identifiers with backticks', function () {
    $driver = new MySqlDriver();
    expect($driver->quoteIdentifier('users'))->toBe('`users`');
    expect($driver->quoteIdentifier('user`name'))->toBe('`user``name`');
});

test('PostgresDriver quotes identifiers with double quotes', function () {
    $driver = new PostgresDriver();
    expect($driver->quoteIdentifier('users'))->toBe('"users"');
    expect($driver->quoteIdentifier('user"name'))->toBe('"user""name"');
});

test('SqliteDriver quotes identifiers with double quotes', function () {
    $driver = new SqliteDriver();
    expect($driver->quoteIdentifier('users'))->toBe('"users"');
});

test('MySqlDriver generates correct DSN', function () {
    $driver = new MySqlDriver();
    $dsn = $driver->getDsn(['host' => 'localhost', 'port' => 3306, 'database' => 'testdb', 'charset' => 'utf8mb4']);
    expect($dsn)->toBe('mysql:host=localhost;port=3306;dbname=testdb;charset=utf8mb4');
});

test('PostgresDriver generates correct DSN', function () {
    $driver = new PostgresDriver();
    $dsn = $driver->getDsn(['host' => 'localhost', 'port' => 5432, 'database' => 'testdb']);
    expect($dsn)->toBe('pgsql:host=localhost;port=5432;dbname=testdb');
});

test('SqliteDriver generates correct DSN', function () {
    $driver = new SqliteDriver();
    expect($driver->getDsn(['database' => ':memory:']))->toBe('sqlite::memory:');
    expect($driver->getDsn([]))->toBe('sqlite::memory:');
});

test('PostgresDriver supports RETURNING', function () {
    expect((new PostgresDriver())->supportsReturning())->toBeTrue();
});

test('MySqlDriver does not support RETURNING', function () {
    expect((new MySqlDriver())->supportsReturning())->toBeFalse();
});

test('SqliteDriver does not support RETURNING', function () {
    expect((new SqliteDriver())->supportsReturning())->toBeFalse();
});

test('drivers generate correct LIMIT OFFSET syntax', function () {
    $drivers = [new MySqlDriver(), new PostgresDriver(), new SqliteDriver()];

    foreach ($drivers as $driver) {
        expect($driver->getLimitOffsetSyntax(10, 20))->toBe('LIMIT 10 OFFSET 20');
    }
});
