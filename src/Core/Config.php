<?php

declare(strict_types=1);

namespace PhpUssd\Core;

use PhpUssd\Exceptions\ConfigurationException;

/**
 * Wraps the app config array with dot-notation access and required-key validation.
 */
final class Config
{
    public function __construct(private readonly array $data) {}

    /**
     * Get a value by dot-notation key.
     * E.g. get('session.driver'), get('menus')
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $parts  = explode('.', $key);
        $cursor = $this->data;

        foreach ($parts as $part) {
            if (!is_array($cursor) || !array_key_exists($part, $cursor)) {
                return $default;
            }
            $cursor = $cursor[$part];
        }

        return $cursor;
    }

    /**
     * Like get(), but throws if key is missing.
     */
    public function require(string $key): mixed
    {
        $value = $this->get($key);

        if ($value === null) {
            throw ConfigurationException::missingKey($key);
        }

        return $value;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function all(): array
    {
        return $this->data;
    }
}
