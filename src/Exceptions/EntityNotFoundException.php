<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\Exceptions;

use DomainException;

/**
 * Thrown when a lookup by primary key / criteria fails.
 *
 * The ErrorHandler maps this to a 404 by exception class — not by matching on
 * the exception message text. This avoids accidentally 404-ing unrelated
 * exceptions whose message happens to contain the phrase "not found".
 */
final class EntityNotFoundException extends DomainException
{
    public function __construct(
        string $message = 'Entity not found.',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
