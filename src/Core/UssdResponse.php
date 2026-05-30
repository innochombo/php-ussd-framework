<?php

declare(strict_types=1);

namespace PhpUssd\Core;

/**
 * Immutable value object for a USSD response.
 *
 * The gateway driver serialises this into whatever format the
 * specific gateway expects (Africa's Talking, Nalo, etc.).
 */
final class UssdResponse
{
    public const TYPE_CON = 'CON';
    public const TYPE_END = 'END';

    private function __construct(
        public readonly string $type,
        public readonly string $body,
    ) {}

    /**
     * Continue the session — user will see a prompt and can type again.
     */
    public static function con(string $body): self
    {
        return new self(self::TYPE_CON, $body);
    }

    /**
     * End the session — screen shows body, session is terminated.
     */
    public static function end(string $body): self
    {
        return new self(self::TYPE_END, $body);
    }

    public function isContinue(): bool
    {
        return $this->type === self::TYPE_CON;
    }

    public function isEnd(): bool
    {
        return $this->type === self::TYPE_END;
    }

    /**
     * Serialise to the standard "CON body" / "END body" string
     * that most gateways accept.
     */
    public function toString(): string
    {
        return "{$this->type} {$this->body}";
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
