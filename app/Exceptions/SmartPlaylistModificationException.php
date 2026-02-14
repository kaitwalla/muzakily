<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

/**
 * Thrown when attempting to modify a smart playlist's songs.
 */
final class SmartPlaylistModificationException extends DomainException
{
    public function __construct(string $message = 'Cannot modify songs in a smart playlist')
    {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return 400;
    }

    public function getErrorCode(): string
    {
        return 'INVALID_OPERATION';
    }
}
