<?php

declare(strict_types=1);

namespace PhpUssd\Gateway;

use PhpUssd\Core\UssdRequest;
use PhpUssd\Core\UssdResponse;
use PhpUssd\Exceptions\UssdException;

/**
 * JSON gateway driver — used by the USSD Phone Simulator and custom HTTP clients.
 *
 * Request (POST, application/json):
 *   { "sessionId": "...", "serviceCode": "*123#", "msisdn": "265888000001", "input": "1" }
 *
 * Response (application/json):
 *   { "type": "CON", "message": "...", "sessionId": "..." }
 *
 * The "input" field carries only the current step's value (not cumulative).
 * It maps directly to UssdRequest::$lastInput — equivalent to Africa's Talking
 * single-step text after splitting on "*".
 */
class JsonDriver implements GatewayDriverInterface
{
    private string $lastSessionId = '';

    public function parse(array $payload): UssdRequest
    {
        $sessionId   = $payload['sessionId']   ?? ($payload['session_id'] ?? null);
        $msisdn      = $payload['msisdn']       ?? ($payload['phoneNumber'] ?? ($payload['phone_number'] ?? null));
        $serviceCode = $payload['serviceCode']  ?? ($payload['service_code'] ?? '');
        $input       = $payload['input']        ?? ($payload['text'] ?? '');

        if (!$sessionId) {
            throw new UssdException("JSON driver: missing 'sessionId' in request body.");
        }

        if (!$msisdn) {
            throw new UssdException("JSON driver: missing 'msisdn' (or 'phoneNumber') in request body.");
        }

        $this->lastSessionId = $sessionId;

        return new UssdRequest(
            sessionId:   $sessionId,
            phoneNumber: $msisdn,
            text:        (string) $input,
            serviceCode: $serviceCode,
            networkCode: '',
        );
    }

    public function serialize(UssdResponse $response): string
    {
        return json_encode([
            'type'      => $response->type,
            'message'   => $response->body,
            'sessionId' => $this->lastSessionId,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    public function sendHeaders(): void
    {
        header('Content-Type: application/json; charset=utf-8');
    }
}
