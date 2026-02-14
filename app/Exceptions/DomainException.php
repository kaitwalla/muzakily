<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Base exception for domain-specific errors.
 * Controllers catch these and convert to appropriate HTTP responses.
 */
abstract class DomainException extends Exception
{
    /**
     * Get the HTTP status code for this exception.
     */
    abstract public function getStatusCode(): int;

    /**
     * Get the error code for API responses.
     */
    abstract public function getErrorCode(): string;
}
