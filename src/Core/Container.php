<?php

declare(strict_types=1);

namespace PhpUssd\Core;

use Closure;
use InvalidArgumentException;

/**
 * Lightweight service container.
 *
 * Supports singleton bindings and factory closures.
 * Not a full PSR-11 container — kept minimal on purpose.
 */
final class Container
{
    /** @var array<string, Closure> */
    private array $bindings = [];

    /** @var array<string, mixed> */
    private array $singletons = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /**
     * Bind a class/key to a factory closure.
     */
    public function bind(string $abstract, Closure $factory): void
    {
        $this->bindings[$abstract] = $factory;
    }

    /**
     * Bind as singleton — factory is called only once, result is cached.
     */
    public function singleton(string $abstract, Closure $factory): void
    {
        $this->bindings[$abstract] = $factory;
        $this->singletons[$abstract] = true;
    }

    /**
     * Register an already-constructed instance as a singleton.
     */
    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    /**
     * Resolve a binding.
     */
    public function make(string $abstract): mixed
    {
        // Return pre-registered instance
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Return cached singleton
        if (isset($this->singletons[$abstract]) && array_key_exists($abstract, $this->instances)) {
            return $this->instances[$abstract];
        }

        if (!isset($this->bindings[$abstract])) {
            throw new InvalidArgumentException("No binding found for '{$abstract}'.");
        }

        $instance = ($this->bindings[$abstract])($this);

        // Cache if singleton
        if (isset($this->singletons[$abstract])) {
            $this->instances[$abstract] = $instance;
        }

        return $instance;
    }

    public function has(string $abstract): bool
    {
        return isset($this->instances[$abstract]) || isset($this->bindings[$abstract]);
    }
}
