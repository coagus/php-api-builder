<?php

declare(strict_types=1);

namespace Tests\Fixtures\Entities;

use Coagus\PhpApiBuilder\Attributes\PrimaryKey;
use Coagus\PhpApiBuilder\Attributes\Table;
use Coagus\PhpApiBuilder\ORM\Entity;
use Coagus\PhpApiBuilder\Validation\Attributes\In;
use Coagus\PhpApiBuilder\Validation\Attributes\MaxLength;
use Coagus\PhpApiBuilder\Validation\Attributes\Required;

/**
 * Fixture for validating `#[In([...])]` end-to-end.
 * The array-form constructor is the documented pattern; this fixture exercises it
 * together with POST/PUT through the APIDB hybrid resource.
 */
#[Table('articles')]
class TestArticle extends Entity
{
    #[PrimaryKey]
    public int $id;

    #[Required, MaxLength(120)]
    public string $title;

    #[Required]
    #[In(['draft', 'published', 'archived'])]
    public string $status;
}
