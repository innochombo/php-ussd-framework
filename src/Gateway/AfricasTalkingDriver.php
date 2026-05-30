<?php

declare(strict_types=1);

namespace PhpUssd\Gateway;

use PhpUssd\Core\UssdRequest;
use PhpUssd\Core\UssdResponse;
use PhpUssd\Exceptions\UssdException;

/**
 * Africa's Talking USSD gateway driver.
 *
 * AT sends POST fields: sessionId, phoneNumber, serviceCode, text
 * AT expects plain text back: "CON ..." or "END ..."
 *
 * @see https://developers.africastalking.com/docs/ussd/handling_sessions
 */
class AfricasTalkingDriver implements GatewayDriverInterface
{
    public function parse(array $payload): UssdRequest
    {
        $sessionId   = $payload['sessionId']   ?? ($payload['session_id']   ?? null);
        $phoneNumber = $payload['phoneNumber']  ?? ($payload['phone_number'] ?? null);
        $serviceCode = $payload['serviceCode']  ?? ($payload['service_code'] ?? '');
        $text        = $payload['text']         ?? '';
        $networkCode = $payload['networkCode']  ?? ($payload['network_code'] ?? '');

        if (!$sessionId) {
            throw new UssdException("Africa's Talking: missing 'sessionId' in payload.");
        }
        if (!$phoneNumber) {
            throw new UssdException("Africa's Talking: missing 'phoneNumber' in payload.");
        }

        return new UssdRequest(
            sessionId:   $sessionId,
            phoneNumber: $phoneNumber,
            text:        $text,
            serviceCode: $serviceCode,
            networkCode: $networkCode,
        );
    }

    public function serialize(UssdResponse $response): string
    {
        // AT expects "CON <body>" or "END <body>" — no extra formatting
        return $response->toString();
    }

    public function sendHeaders(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
    }
}
