<?php

declare(strict_types=1);

namespace PhpUssd\Gateway;

use PhpUssd\Core\UssdRequest;
use PhpUssd\Core\UssdResponse;
use PhpUssd\Exceptions\UssdException;

/**
 * Nalo Solutions USSD gateway driver.
 *
 * Nalo sends POST fields: msisdn, sessionid, userdata, network, msgtype
 * Nalo expects plain text: "CON ..." or "END ..."
 *
 * msgtype values: 1 = initiation, 2 = response, 3 = release, 4 = timeout
 */
class NaloDriver implements GatewayDriverInterface
{
    public function parse(array $payload): UssdRequest
    {
        $sessionId   = $payload['sessionid'] ?? ($payload['sessionId'] ?? null);
        $phoneNumber = $payload['msisdn']    ?? null;
        $text        = $payload['userdata']  ?? '';
        $networkCode = $payload['network']   ?? '';

        if (!$sessionId) {
            throw new UssdException("Nalo: missing 'sessionid' in payload.");
        }
        if (!$phoneNumber) {
            throw new UssdException("Nalo: missing 'msisdn' in payload.");
        }

        return new UssdRequest(
            sessionId:   $sessionId,
            phoneNumber: $phoneNumber,
            text:        $text,
            serviceCode: '',
            networkCode: $networkCode,
        );
    }

    public function serialize(UssdResponse $response): string
    {
        return $response->toString();
    }

    public function sendHeaders(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
    }
}
