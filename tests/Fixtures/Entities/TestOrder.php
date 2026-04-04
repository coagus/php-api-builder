<?php

declare(strict_types=1);

namespace Tests\Fixtures\Entities;

use Coagus\PhpApiBuilder\Attributes\BelongsTo;
use Coagus\PhpApiBuilder\Attributes\PrimaryKey;
use Coagus\PhpApiBuilder\Attributes\Table;
use Coagus\PhpApiBuilder\ORM\Entity;
use Coagus\PhpApiBuilder\Validation\Attributes\Required;

#[Table('orders')]
class TestOrder extends Entity
{
    #[PrimaryKey]
    public int $id;

    #[Required]
    public int $userId;

    #[Required]
    public float $total;

    public ?string $status = 'pending';

    #[BelongsTo(TestUser::class)]
    public ?object $user = null;
}
