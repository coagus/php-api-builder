<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\ORM\Connection;
use Tests\Fixtures\Entities\TestAccount;

beforeEach(function () {
    Connection::reset();
    Connection::configure(['driver' => 'sqlite', 'database' => ':memory:']);
    Connection::getInstance()->exec(
        'CREATE TABLE accounts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL,
            password_hash TEXT NOT NULL DEFAULT ""
        )'
    );
    TestAccount::clearMetadataCache();
});

test('#[Ignore] property drives a write-only hook without being persisted', function () {
    $account = new TestAccount();
    $account->email = 'alice@example.com';
    $account->password = 'correct horse battery staple';

    // The hook populated the backing column ...
    expect($account->passwordHash)->toStartWith('$argon2id$');

    // ... and `save()` persists only the hash column.
    $account->save();

    $row = Connection::getInstance()->query('SELECT * FROM accounts LIMIT 1')[0];
    expect($row)->toHaveKey('password_hash')
        ->and($row)->not->toHaveKey('password')
        ->and($row['password_hash'])->toStartWith('$argon2id$');
});

test('fill() does not invoke the ignored hook from an incoming request body', function () {
    $account = new TestAccount();

    // Simulate a controller fill() from request JSON that happens to contain
    // an ignored field by mistake. The hook must NOT run, because #[Ignore]
    // removes the property from the hydrator/fill pass.
    $account->fill([
        'email' => 'bob@example.com',
        'password' => 'plaintext-should-not-hash-here',
        'passwordHash' => 'preset-hash-value',
    ]);

    expect($account->email)->toBe('bob@example.com')
        ->and($account->passwordHash)->toBe('preset-hash-value');
});

test('hydrate() does not re-run the hook when loading rows from DB', function () {
    $account = new TestAccount();
    $account->email = 'carol@example.com';
    $account->password = 'original-secret';
    $savedHash = $account->passwordHash;
    $account->save();

    // Clear metadata and load back — the ignored hook MUST NOT run, otherwise
    // the hash would be re-hashed (see the issue report) and login comparison
    // would break.
    TestAccount::clearMetadataCache();
    $loaded = TestAccount::find($account->id);

    expect($loaded)->not->toBeNull()
        ->and($loaded->passwordHash)->toBe($savedHash);
    // password_verify proves the stored hash still matches the original secret.
    expect(password_verify('original-secret', $loaded->passwordHash))->toBeTrue();
});

test('toArray() omits an #[Ignore] property from the serialized shape', function () {
    $account = new TestAccount();
    $account->email = 'dave@example.com';
    $account->password = 'whatever';

    $arr = $account->toArray();

    expect($arr)->toHaveKey('email')
        ->and($arr)->not->toHaveKey('password');
});
