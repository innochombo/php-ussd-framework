<?php

declare(strict_types=1);

namespace PhpUssd\Session;

/**
 * Provides dot-notation get/set/forget on top of a plain $data array.
 * Concrete drivers only need to implement load(), save(), destroy().
 */
abstract class AbstractSessionManager implements SessionManagerInterface
{
    protected string $sessionId = '';
    protected array  $data      = [];
    protected bool   $loaded    = false;
    protected bool   $dirty     = false;

    public function load(string $sessionId): void
    {
        $this->sessionId = $sessionId;
        $this->data      = $this->read($sessionId);
        $this->loaded    = true;
        $this->dirty     = false;
    }

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

    public function set(string $key, mixed $value): void
    {
        $parts   = explode('.', $key);
        $cursor  = &$this->data;

        foreach ($parts as $i => $part) {
            if ($i === count($parts) - 1) {
                $cursor[$part] = $value;
            } else {
                if (!isset($cursor[$part]) || !is_array($cursor[$part])) {
                    $cursor[$part] = [];
                }
                $cursor = &$cursor[$part];
            }
        }

        $this->dirty = true;
    }

    public function forget(string $key): void
    {
        $parts  = explode('.', $key);
        $cursor = &$this->data;

        foreach ($parts as $i => $part) {
            if ($i === count($parts) - 1) {
                unset($cursor[$part]);
                $this->dirty = true;
                return;
            }
            if (!isset($cursor[$part]) || !is_array($cursor[$part])) {
                return;
            }
            $cursor = &$cursor[$part];
        }
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function all(): array
    {
        return $this->data;
    }

    public function save(): void
    {
        if ($this->dirty) {
            $this->write($this->sessionId, $this->data);
            $this->dirty = false;
        }
    }

    /**
     * Read raw data from the backing store.
     * Return an empty array if session does not exist yet.
     */
    abstract protected function read(string $sessionId): array;

    /**
     * Write raw data to the backing store.
     */
    abstract protected function write(string $sessionId, array $data): void;
}
