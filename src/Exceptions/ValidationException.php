<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\Exceptions;

use DomainException;

/**
 * Thrown when entity validation fails.
 *
 * Carries a structured map of field -> list of error messages so callers can
 * render a proper RFC 7807 422 response without parsing the exception message.
 */
final class ValidationException extends DomainException
{
    /**
     * @param array<string, list<string>> $errors
     */
    public function __construct(
        public readonly array $errors,
        string $message = 'Validation failed.',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
