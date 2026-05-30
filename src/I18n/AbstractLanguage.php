<?php

declare(strict_types=1);

namespace PhpUssd\I18n;

/**
 * Convenience base class for languages that store translations in a PHP array.
 *
 * Subclass this and define $translations:
 *
 *   class English extends AbstractLanguage {
 *       protected string $code = 'en';
 *       protected string $name = 'English';
 *       protected array $translations = [
 *           'welcome' => 'Welcome to MyApp',
 *           'back'    => 'Back',
 *       ];
 *   }
 */
abstract class AbstractLanguage implements LanguageInterface
{
    protected string $code = 'en';
    protected string $name = 'English';

    /** @var array<string, string> */
    protected array $translations = [];

    public function get(string $key): string
    {
        return $this->translations[$key] ?? "[missing:{$key}]";
    }

    public function code(): string
    {
        return $this->code;
    }

    public function name(): string
    {
        return $this->name;
    }
}
