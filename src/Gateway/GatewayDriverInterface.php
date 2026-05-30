<?php

declare(strict_types=1);

namespace PhpUssd\Gateway;

use PhpUssd\Core\UssdRequest;
use PhpUssd\Core\UssdResponse;

/**
 * Each USSD gateway (Africa's Talking, Nalo, etc.) has a different
 * POST payload format and expects a different response format.
 *
 * A GatewayDriver normalises the incoming payload into a UssdRequest
 * and serialises a UssdResponse back to whatever the gateway wants.
 */
interface GatewayDriverInterface
{
    /**
     * Parse the raw POST (or GET) array into a UssdRequest.
     */
    public function parse(array $payload): UssdRequest;

    /**
     * Serialise a UssdResponse for this gateway.
     * Returns the string that should be echoed back.
     */
    public function serialize(UssdResponse $response): string;

    /**
     * Set any required response headers for this gateway.
     * Called before output is sent.
     */
    public function sendHeaders(): void;
}
