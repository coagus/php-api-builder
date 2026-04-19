<?php

declare(strict_types=1);

namespace Tests\Fixtures\Entities;

use Coagus\PhpApiBuilder\Attributes\Ignore;
use Coagus\PhpApiBuilder\Attributes\PrimaryKey;
use Coagus\PhpApiBuilder\Attributes\Table;
use Coagus\PhpApiBuilder\ORM\Entity;
use Coagus\PhpApiBuilder\Validation\Attributes\Hidden;

/**
 * Fixture for `#[Ignore]` coverage.
 *
 * `password` is a virtual write-only hook: assigning a plaintext value hashes
 * it and stores it in `passwordHash`. The property itself is `#[Ignore]`d so
 * the ORM, validator and schema generator never read it back.
 */
#[Table('accounts')]
class TestAccount extends Entity
{
    #[PrimaryKey]
    public int $id;

    public string $email;

    #[Hidden]
    public string $passwordHash = '';

    #[Ignore]
    public string $password {
        set {
            $this->passwordHash = password_hash($value, PASSWORD_ARGON2ID);
        }
    }
}
