<?php

declare(strict_types=1);

namespace PhpUssd\Exceptions;

class SessionException extends UssdException
{
    public static function failedToRead(string $sessionId, string $reason): self
    {
        return new self("Failed to read session '{$sessionId}': {$reason}");
    }

    public static function failedToWrite(string $sessionId, string $reason): self
    {
        return new self("Failed to write session '{$sessionId}': {$reason}");
    }
}
