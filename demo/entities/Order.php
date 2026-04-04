<?php

declare(strict_types=1);

namespace DemoApi\Entities;

use Coagus\PhpApiBuilder\Attributes\BelongsTo;
use Coagus\PhpApiBuilder\Attributes\PrimaryKey;
use Coagus\PhpApiBuilder\Attributes\Table;
use Coagus\PhpApiBuilder\ORM\Entity;
use Coagus\PhpApiBuilder\Validation\Attributes\In;
use Coagus\PhpApiBuilder\Validation\Attributes\Min;
use Coagus\PhpApiBuilder\Validation\Attributes\Required;

#[Table('orders')]
class Order extends Entity
{
    #[PrimaryKey]
    public int $id;

    #[Required]
    public int $userId;

    #[Required, Min(0)]
    public float $total;

    #[In('pending', 'completed', 'cancelled')]
    public string $status = 'pending';

    #[BelongsTo(User::class, 'user_id')]
    public ?User $user = null;
}
