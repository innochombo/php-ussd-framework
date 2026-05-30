<?php

declare(strict_types=1);

namespace PhpUssd\Http;

use PhpUssd\Exceptions\UssdException;

/**
 * Thin cURL wrapper for making API calls from menus.
 *
 * Features over the original ApiHelper:
 *  - Automatic retry with exponential back-off
 *  - Per-request timeout override
 *  - Non-2xx responses treated as errors (configurable)
 *  - Typed response via HttpResponse value object
 */
class HttpClient
{
    private array  $defaultHeaders = ['Content-Type' => 'application/json', 'Accept' => 'application/json'];
    private int    $timeout;
    private int    $retries;
    private bool   $throwOnError;
    private string $baseUrl;

    public function __construct(
        string $baseUrl = '',
        int    $timeout = 30,
        int    $retries = 1,
        bool   $throwOnError = false,
    ) {
        $this->baseUrl      = rtrim($baseUrl, '/');
        $this->timeout      = $timeout;
        $this->retries      = max(0, $retries);
        $this->throwOnError = $throwOnError;
    }

    public function withHeaders(array $headers): static
    {
        $clone = clone $this;
        $clone->defaultHeaders = array_merge($clone->defaultHeaders, $headers);
        return $clone;
    }

    public function withToken(string $token): static
    {
        return $this->withHeaders(['Authorization' => "Bearer {$token}"]);
    }

    public function get(string $endpoint, array $params = []): HttpResponse
    {
        return $this->send('GET', $endpoint, $params);
    }

    public function post(string $endpoint, array $body = [], array $params = []): HttpResponse
    {
        return $this->send('POST', $endpoint, $params, $body);
    }

    public function put(string $endpoint, array $body = [], array $params = []): HttpResponse
    {
        return $this->send('PUT', $endpoint, $params, $body);
    }

    public function delete(string $endpoint, array $params = []): HttpResponse
    {
        return $this->send('DELETE', $endpoint, $params);
    }

    private function send(string $method, string $endpoint, array $params = [], array $body = []): HttpResponse
    {
        $url = $this->buildUrl($endpoint, $params);
        $attempts = 0;
        $lastError = '';

        do {
            $attempts++;
            $result = $this->execute($method, $url, $body);

            if ($result !== null) {
                $response = $result;

                if ($this->throwOnError && $response->status >= 400) {
                    throw new UssdException("HTTP {$response->status} on {$method} {$url}");
                }

                return $response;
            }

            // Exponential back-off before retry (50ms, 100ms, 200ms…)
            if ($attempts <= $this->retries) {
                usleep((int) (50_000 * (2 ** ($attempts - 1))));
            }

            $lastError = 'cURL error';
        } while ($attempts <= $this->retries);

        return HttpResponse::error($lastError);
    }

    private function execute(string $method, string $url, array $body): ?HttpResponse
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => $this->formatHeaders(),
        ]);

        match ($method) {
            'POST'   => $this->applyBody($ch, 'POST', $body),
            'PUT'    => $this->applyBody($ch, 'PUT',  $body),
            'DELETE' => curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE'),
            default  => curl_setopt($ch, CURLOPT_HTTPGET, true),
        };

        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);

        curl_close($ch);

        if ($error) {
            error_log("[PhpUssd HttpClient] cURL error on {$method} {$url}: {$error}");
            return null;
        }

        return HttpResponse::fromRaw($status, $raw ?: '');
    }

    private function applyBody($ch, string $method, array $body): void
    {
        if ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        } else {
            curl_setopt($ch, CURLOPT_POST, true);
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    private function buildUrl(string $endpoint, array $params): string
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
        return empty($params) ? $url : $url . '?' . http_build_query($params);
    }

    private function formatHeaders(): array
    {
        $lines = [];
        foreach ($this->defaultHeaders as $key => $value) {
            $lines[] = "{$key}: {$value}";
        }
        return $lines;
    }
}
