<?php

declare(strict_types=1);

namespace PhpUssd\I18n;

interface LanguageInterface
{
    /**
     * Return the translated string for the given key.
     * If the key is missing, return a visible placeholder so developers
     * notice immediately rather than silently showing empty strings.
     */
    public function get(string $key): string;

    /**
     * ISO 639-1 language code, e.g. "en", "ny", "fr".
     */
    public function code(): string;

    /**
     * Human-readable language name, e.g. "English", "Chichewa".
     */
    public function name(): string;
}
