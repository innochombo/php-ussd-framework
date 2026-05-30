<?php

declare(strict_types=1);

namespace PhpUssd\I18n;

use InvalidArgumentException;

/**
 * Holds all registered languages and exposes the active one.
 *
 * Languages are registered lazily — the class is only instantiated
 * when that language is first accessed.
 */
class LanguageManager
{
    /** @var array<string, class-string<LanguageInterface>> */
    private array $registry = [];

    /** @var array<string, LanguageInterface> */
    private array $instances = [];

    private string $activeCode;

    /**
     * @param array<string, class-string<LanguageInterface>> $languages  e.g. ['en' => English::class]
     * @param string $defaultCode
     */
    public function __construct(array $languages, string $defaultCode = 'en')
    {
        foreach ($languages as $code => $class) {
            $this->register($code, $class);
        }
        $this->activeCode = $defaultCode;
    }

    public function register(string $code, string $class): void
    {
        $this->registry[$code] = $class;
    }

    /**
     * Switch the active language. Persisting this choice is the
     * application's responsibility (store in session).
     */
    public function setActive(string $code): void
    {
        if (!isset($this->registry[$code])) {
            throw new InvalidArgumentException("Language '{$code}' is not registered.");
        }
        $this->activeCode = $code;
    }

    public function activeCode(): string
    {
        return $this->activeCode;
    }

    /**
     * Translate a key using the currently active language.
     */
    public function get(string $key): string
    {
        return $this->active()->get($key);
    }

    /**
     * Like get() but runs sprintf() with extra args.
     */
    public function format(string $key, mixed ...$args): string
    {
        return sprintf($this->get($key), ...$args);
    }

    public function active(): LanguageInterface
    {
        return $this->resolve($this->activeCode);
    }

    /**
     * Returns [code => name] map for building a language-selection menu.
     */
    public function availableLanguages(): array
    {
        $result = [];
        foreach ($this->registry as $code => $class) {
            $result[$code] = $this->resolve($code)->name();
        }
        return $result;
    }

    private function resolve(string $code): LanguageInterface
    {
        if (!isset($this->instances[$code])) {
            if (!isset($this->registry[$code])) {
                throw new InvalidArgumentException("Language '{$code}' is not registered.");
            }
            $class = $this->registry[$code];
            $this->instances[$code] = new $class();
        }
        return $this->instances[$code];
    }
}
