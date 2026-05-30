<?php

declare(strict_types=1);

namespace PhpUssd\Http;

/**
 * Typed wrapper for an HTTP response.
 *
 * Eliminates the raw array returns from the original ApiHelper
 * and gives menus a clean, self-documenting API.
 */
final class HttpResponse
{
    private function __construct(
        public readonly int     $status,
        public readonly ?array  $data,
        public readonly ?string $error,
        public readonly string  $raw,
    ) {}

    public static function fromRaw(int $status, string $raw): self
    {
        $data = null;
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data = $decoded;
            }
        }

        return new self($status, $data, null, $raw);
    }

    public static function error(string $message): self
    {
        return new self(0, null, $message, '');
    }

    public function ok(): bool
    {
        return $this->status >= 200 && $this->status < 300 && $this->error === null;
    }

    public function failed(): bool
    {
        return !$this->ok();
    }

    /**
     * Safely get a nested value from the JSON data.
     * E.g. $response->get('tokenData.token')
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->data === null) {
            return $default;
        }

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
     * True if the response data contains an 'errors' key (common API pattern).
     */
    public function hasErrors(): bool
    {
        return $this->data !== null && isset($this->data['errors']);
    }
}
