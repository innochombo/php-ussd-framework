<?php

declare(strict_types=1);

namespace PhpUssd\Core;

interface MiddlewareInterface
{
    public function process(array $payload, callable $next): string;
}
