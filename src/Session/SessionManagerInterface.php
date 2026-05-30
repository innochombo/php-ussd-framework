<?php

declare(strict_types=1);

namespace PhpUssd\Session;

/**
 * All session drivers implement this contract.
 *
 * Keys support dot notation: set('user.token', '…') nests under 'user'.
 * Reads are lazy; writes are accumulated in memory and flushed via save().
 */
interface SessionManagerInterface
{
    /**
     * Load session data for the given session ID.
     * Must be called before any get/set operations.
     */
    public function load(string $sessionId): void;

    /**
     * Get a value. Supports dot-notation.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Set a value. Supports dot-notation.
     * Does NOT write to the backing store — call save() for that.
     */
    public function set(string $key, mixed $value): void;

    /**
     * Remove a key. Supports dot-notation.
     */
    public function forget(string $key): void;

    /**
     * Check if a key exists and is not null.
     */
    public function has(string $key): bool;

    /**
     * Return all session data as a flat array.
     */
    public function all(): array;

    /**
     * Persist accumulated changes to the backing store.
     * Called once at the end of a request, not on every set().
     */
    public function save(): void;

    /**
     * Destroy the session entirely (on logout or end of USSD session).
     */
    public function destroy(): void;
}
