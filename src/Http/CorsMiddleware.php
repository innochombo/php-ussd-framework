<?php

declare(strict_types=1);

namespace PhpUssd\Http;

use PhpUssd\Core\MiddlewareInterface;

final class CorsMiddleware implements MiddlewareInterface
{
    private array $allowedOrigins;
    private array $allowedMethods;
    private array $allowedHeaders;
    private bool $allowCredentials;
    private int $maxAge;

    public function __construct(array $options = [])
    {
        $this->allowedOrigins   = $this->normalizeList($options['allow_origins'] ?? ['*']);
        $this->allowedMethods   = $this->normalizeList($options['allow_methods'] ?? ['GET', 'POST', 'OPTIONS']);
        $this->allowedHeaders   = $this->normalizeList($options['allow_headers'] ?? ['Content-Type', 'X-Requested-With']);
        $this->allowCredentials = (bool) ($options['allow_credentials'] ?? false);
        $this->maxAge           = (int) ($options['max_age'] ?? 600);
    }

    public function process(array $payload, callable $next): string
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if ($origin !== '' && !$this->isOriginAllowed($origin)) {
            return $next($payload);
        }

        if ($origin !== '') {
            $this->sendCorsHeaders($origin);
        }

        if (strcasecmp($_SERVER['REQUEST_METHOD'] ?? 'GET', 'OPTIONS') === 0) {
            return '';
        }

        return $next($payload);
    }

    private function sendCorsHeaders(string $origin): void
    {
        if ($this->allowsAnyOrigin()) {
            header('Access-Control-Allow-Origin: *');
        } else {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
        }

        header('Access-Control-Allow-Methods: ' . implode(', ', $this->allowedMethods));
        header('Access-Control-Allow-Headers: ' . implode(', ', $this->allowedHeaders));

        if ($this->allowCredentials) {
            header('Access-Control-Allow-Credentials: true');
        }

        if ($this->maxAge > 0) {
            header('Access-Control-Max-Age: ' . $this->maxAge);
        }
    }

    private function normalizeList(string|array $values): array
    {
        if (is_string($values)) {
            return array_map('trim', explode(',', $values));
        }

        return array_map('trim', array_values($values));
    }

    private function isOriginAllowed(string $origin): bool
    {
        return $this->allowsAnyOrigin() || in_array($origin, $this->allowedOrigins, true);
    }

    private function allowsAnyOrigin(): bool
    {
        return in_array('*', $this->allowedOrigins, true);
    }
}
