<?php

declare(strict_types=1);

namespace PhpUssd\Session;

/**
 * In-memory session driver — for unit tests and local development.
 *
 * Data is never written to disk or any external store.
 * Multiple calls to load() within the same process share data
 * via the static $store map, mimicking real session behaviour
 * across multiple simulated requests in the same test run.
 */
class ArraySessionManager extends AbstractSessionManager
{
    /** @var array<string, array> */
    private static array $store = [];

    public function destroy(): void
    {
        unset(self::$store[$this->sessionId]);
        $this->data   = [];
        $this->loaded = false;
        $this->dirty  = false;
    }

    /**
     * Reset the entire store — call in test setUp() to get a clean slate.
     */
    public static function flush(): void
    {
        self::$store = [];
    }

    protected function read(string $sessionId): array
    {
        return self::$store[$sessionId] ?? [];
    }

    protected function write(string $sessionId, array $data): void
    {
        self::$store[$sessionId] = $data;
    }
}
