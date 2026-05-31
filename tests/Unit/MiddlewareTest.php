<?php

declare(strict_types=1);

namespace PhpUssd\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PhpUssd\Http\CorsMiddleware;

class MiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        if (function_exists('header_remove')) {
            header_remove();
        }

        $_SERVER['HTTP_ORIGIN'] = 'http://localhost:5173';
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
    }

    protected function tearDown(): void
    {
        if (function_exists('header_remove')) {
            header_remove();
        }
    }

    public function test_cors_middleware_handles_preflight(): void
    {
        $middleware = new CorsMiddleware([
            'allow_origins' => ['http://localhost:5173'],
            'allow_methods' => ['GET', 'POST', 'OPTIONS'],
            'allow_headers' => ['Content-Type', 'X-Requested-With'],
            'allow_credentials' => false,
            'max_age' => 600,
        ]);

        $result = $middleware->process([], fn(array $payload): string => 'next');

        $this->assertSame('', $result);
        $headers = headers_list();

        $this->assertContains('Access-Control-Allow-Origin: http://localhost:5173', $headers);
        $this->assertContains('Access-Control-Allow-Methods: GET, POST, OPTIONS', $headers);
        $this->assertContains('Access-Control-Allow-Headers: Content-Type, X-Requested-With', $headers);
        $this->assertContains('Access-Control-Max-Age: 600', $headers);
    }

    public function test_cors_middleware_allows_non_options_requests(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $middleware = new CorsMiddleware([
            'allow_origins' => ['http://localhost:5173'],
            'allow_methods' => ['GET', 'POST', 'OPTIONS'],
            'allow_headers' => ['Content-Type', 'X-Requested-With'],
        ]);

        $result = $middleware->process(['foo' => 'bar'], fn(array $payload): string => 'next:' . $payload['foo']);

        $this->assertSame('next:bar', $result);
        $headers = headers_list();
        $this->assertContains('Access-Control-Allow-Origin: http://localhost:5173', $headers);
    }
}
