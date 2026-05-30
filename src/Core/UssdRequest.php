<?php

declare(strict_types=1);

namespace PhpUssd\Core;

/**
 * Immutable value object representing a single USSD request.
 *
 * The framework normalises all gateway payloads into this object,
 * so menu code never needs to know which gateway is in use.
 */
final class UssdRequest
{
    /**
     * The full input string as accumulated by the gateway (e.g. "1*2*3").
     * Individual step inputs are separated by "*".
     */
    public readonly string $text;

    /**
     * Only the most-recent input segment — the part after the last "*".
     * This is what menus use 99% of the time.
     */
    public readonly string $lastInput;

    public function __construct(
        public readonly string $sessionId,
        public readonly string $phoneNumber,
        string $text,
        public readonly string $serviceCode = '',
        public readonly string $networkCode = '',
    ) {
        $this->text      = $text ?? '';
        $this->lastInput = $this->resolveLastInput($this->text);
    }

    public function isInitial(): bool
    {
        return $this->text === '';
    }

    public function isBack(): bool
    {
        return $this->lastInput === '0';
    }

    public function isMainMenu(): bool
    {
        return $this->lastInput === '00';
    }

    /**
     * Pagination: "99" = next page, "98" = previous page.
     *
     * We deliberately avoid '*' and '#' as pagination inputs because
     * Africa's Talking uses '*' as the segment delimiter — a literal '*'
     * as user input results in an empty lastInput after splitting.
     * "99" and "98" are conventional USSD pagination inputs.
     */
    public function isNextPage(): bool
    {
        return $this->lastInput === '99';
    }

    public function isPrevPage(): bool
    {
        return $this->lastInput === '98';
    }

    /**
     * Returns all input segments as an array.
     * E.g. "1*2*3" → ["1", "2", "3"]
     */
    public function inputSegments(): array
    {
        return $this->text === '' ? [] : explode('*', $this->text);
    }

    private function resolveLastInput(string $text): string
    {
        if ($text === '') {
            return '';
        }
        $parts = explode('*', $text);
        return end($parts);
    }
}
