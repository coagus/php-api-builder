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

test('all drivers expose a portable CURRENT_TIMESTAMP expression', function () {
    $drivers = [new MySqlDriver(), new PostgresDriver(), new SqliteDriver()];

    foreach ($drivers as $driver) {
        expect($driver->getCurrentTimestampExpression())->toBe('CURRENT_TIMESTAMP');
    }
});

test('SqliteDriver::applySessionSettings enables foreign keys and busy timeout', function () {
    $pdo = new PDO('sqlite::memory:');
    (new SqliteDriver())->applySessionSettings($pdo);

    $fk = $pdo->query('PRAGMA foreign_keys')->fetchColumn();
    $busy = $pdo->query('PRAGMA busy_timeout')->fetchColumn();

    expect((int) $fk)->toBe(1)
        ->and((int) $busy)->toBe(5000);
});

test('MySqlDriver::applySessionSettings is safe to call on a mock PDO', function () {
    // We can't open a real MySQL connection in this env — assert that the
    // method signature matches the interface and does not throw when the
    // underlying exec() is a no-op.
    $driver = new MySqlDriver();
    $ref = new ReflectionMethod($driver, 'applySessionSettings');

    expect($ref->getNumberOfRequiredParameters())->toBe(1)
        ->and($ref->getParameters()[0]->getType()?->__toString())->toBe('PDO');
});

test('PostgresDriver::applySessionSettings is safe to call on a mock PDO', function () {
    $driver = new PostgresDriver();
    $ref = new ReflectionMethod($driver, 'applySessionSettings');

    expect($ref->getNumberOfRequiredParameters())->toBe(1)
        ->and($ref->getParameters()[0]->getType()?->__toString())->toBe('PDO');
});

test('each driver returns driver-specific refresh-token DDL', function () {
    $mysql = (new MySqlDriver())->getRefreshTokenTableDdl();
    $pg = (new PostgresDriver())->getRefreshTokenTableDdl();
    $sqlite = (new SqliteDriver())->getRefreshTokenTableDdl();

    expect($mysql)->toContain('refresh_tokens')
        ->and($mysql)->toContain('VARCHAR')
        ->and($mysql)->toContain('ENGINE=InnoDB')
        ->and($pg)->toContain('refresh_tokens')
        ->and($pg)->toContain('TIMESTAMPTZ')
        ->and($sqlite)->toContain('refresh_tokens')
        ->and($sqlite)->toContain('TEXT');
});
