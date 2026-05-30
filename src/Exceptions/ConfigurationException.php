<?php

declare(strict_types=1);

namespace PhpUssd\Exceptions;

class ConfigurationException extends UssdException
{
    public static function missingKey(string $key): self
    {
        return new self("Required configuration key '{$key}' is missing.");
    }

    public static function invalidDriver(string $key, string $class): self
    {
        return new self("Configuration '{$key}' points to '{$class}' which does not exist.");
    }
}
