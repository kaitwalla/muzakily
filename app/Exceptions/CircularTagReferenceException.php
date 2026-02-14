<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

/**
 * Thrown when a tag hierarchy would create a circular reference.
 */
final class CircularTagReferenceException extends DomainException
{
    public function __construct(string $message = 'This would create a circular tag reference')
    {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return 422;
    }

    public function getErrorCode(): string
    {
        return 'CIRCULAR_REFERENCE';
    }
}
