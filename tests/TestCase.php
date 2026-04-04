<?php

namespace Tests;

use Coagus\PhpApiBuilder\ORM\Connection;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected static Connection $db;

    public static function setUpDatabase(): void
    {
        Connection::configure([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        self::$db = Connection::getInstance();
        self::$db->exec(file_get_contents(__DIR__ . '/Fixtures/migrations.sql'));
    }
}
