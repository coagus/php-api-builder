<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\ORM\DsnParser;

test('parses a standard postgresql URI with explicit port', function () {
    $fields = DsnParser::parse('postgresql://alice:s3cret@db.example.com:6543/postgres');

    expect($fields)->toMatchArray([
        'driver'   => 'pgsql',
        'host'     => 'db.example.com',
        'port'     => 6543,
        'database' => 'postgres',
        'username' => 'alice',
        'password' => 's3cret',
    ]);
});

test('accepts the shorter postgres:// scheme', function () {
    $fields = DsnParser::parse('postgres://u:p@h/d');

    expect($fields['driver'])->toBe('pgsql');
    expect($fields['port'])->toBe(5432);
});

test('defaults port 5432 for postgres when the URI omits it', function () {
    $fields = DsnParser::parse('postgresql://u:p@host/db');

    expect($fields['port'])->toBe(5432);
});

test('routes mysql:// scheme to the mysql driver', function () {
    $fields = DsnParser::parse('mysql://u:p@h:3306/d');

    expect($fields['driver'])->toBe('mysql');
    expect($fields['port'])->toBe(3306);
});

test('treats mariadb:// as an alias for mysql', function () {
    $fields = DsnParser::parse('mariadb://u:p@h/d');

    expect($fields['driver'])->toBe('mysql');
    expect($fields['port'])->toBe(3306);
});

test('defaults port 3306 for mysql when the URI omits it', function () {
    $fields = DsnParser::parse('mysql://u:p@host/db');

    expect($fields['port'])->toBe(3306);
});

test('URL-decodes username and password (Supabase passwords often contain @ and other reserved chars)', function () {
    $fields = DsnParser::parse('postgresql://name%40org:pa%23ss%21@host/db');

    expect($fields['username'])->toBe('name@org');
    expect($fields['password'])->toBe('pa#ss!');
});

test('drops query-string options — libpq negotiates TLS automatically', function () {
    $fields = DsnParser::parse('postgresql://u:p@h:5432/d?sslmode=require&application_name=foo');

    expect($fields)->toMatchArray([
        'host'     => 'h',
        'port'     => 5432,
        'database' => 'd',
    ]);
});

test('supports URIs without credentials (peer auth / pgpass)', function () {
    $fields = DsnParser::parse('postgresql://host/db');

    expect($fields['username'])->toBeNull();
    expect($fields['password'])->toBeNull();
    expect($fields['host'])->toBe('host');
    expect($fields['database'])->toBe('db');
});

test('supports URIs with username but no password', function () {
    $fields = DsnParser::parse('postgresql://alice@host/db');

    expect($fields['username'])->toBe('alice');
    expect($fields['password'])->toBeNull();
});

test('returns empty database when the URI omits the path', function () {
    $fields = DsnParser::parse('postgresql://u:p@host');

    expect($fields['database'])->toBe('');
});

test('rejects an unrecognised scheme', function () {
    DsnParser::parse('oracle://u:p@h/d');
})->throws(InvalidArgumentException::class);

test('rejects a malformed URI', function () {
    DsnParser::parse('not-a-uri');
})->throws(InvalidArgumentException::class);

test('looksLikeUri detects supported schemes', function () {
    expect(DsnParser::looksLikeUri('postgresql://u@h/d'))->toBeTrue();
    expect(DsnParser::looksLikeUri('postgres://u@h/d'))->toBeTrue();
    expect(DsnParser::looksLikeUri('mysql://u@h/d'))->toBeTrue();
    expect(DsnParser::looksLikeUri('mariadb://u@h/d'))->toBeTrue();

    expect(DsnParser::looksLikeUri('pgsql:host=h;dbname=d'))->toBeFalse();
    expect(DsnParser::looksLikeUri('sqlite::memory:'))->toBeFalse();
    expect(DsnParser::looksLikeUri('oracle://u@h/d'))->toBeFalse();
    expect(DsnParser::looksLikeUri(''))->toBeFalse();
});
