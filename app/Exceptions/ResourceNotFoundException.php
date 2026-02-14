<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Thrown when a requested resource cannot be found.
 */
final class ResourceNotFoundException extends \DomainException
{
    public function __construct(string $message = 'Resource not found')
    {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return 404;
    }

    public function getErrorCode(): string
    {
        return 'NOT_FOUND';
    }
}
