<?php

declare(strict_types=1);

if (!function_exists('env')) {
    function env(string $name, mixed $default = null): mixed
    {
        if (array_key_exists($name, $_ENV) && $_ENV[$name] !== null) {
            return $_ENV[$name];
        }

        if (array_key_exists($name, $_SERVER) && $_SERVER[$name] !== null) {
            return $_SERVER[$name];
        }

        return $default;
    }
}

ini_set('log_errors', '1');
ini_set('error_log', 'php://stderr');

require_once __DIR__ . '/vendor/autoload.php';

// MenuIds constants must be required explicitly — they are not PSR-4 autoloaded.
require_once __DIR__ . '/example/config/MenuIds.php';

$app = new \PhpUssd\Core\Application(require __DIR__ . '/example/config/app.php');

// PHP only auto-populates $_POST for form-encoded bodies.
// JSON bodies (USSD simulator, REST clients) are read from php://input.
$contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $payload = ($raw !== false && $raw !== '') ? (json_decode($raw, true) ?? []) : [];
} else {
    $payload = $_POST ?: $_GET;
}

echo $app->run($payload);
