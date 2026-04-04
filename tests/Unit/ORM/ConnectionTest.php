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
