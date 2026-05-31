<?php

declare(strict_types=1);

/**
 * USSD Application Entry Point
 *
 * Before (original): ~20 lines of bootstrap + wiring
 * After  (framework): 3 lines.
 */

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
ini_set('error_log', __DIR__ . '/phpussd.log');

require_once __DIR__ . '/vendor/autoload.php';

// Minimal, early CORS handling for dev (preflight + simple requests).
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = [$origin ?: 'http://localhost:5173'];
if ($origin && in_array($origin, $allowed, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: ' . ($allowed[0] ?? '*'));
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if (strcasecmp($_SERVER['REQUEST_METHOD'] ?? 'GET', 'OPTIONS') === 0) {
    // Short-circuit preflight requests before booting the app
    http_response_code(200);
    exit(0);
}

// Load your app config's MenuIds so they are available globally
require_once __DIR__ . '/example/config/MenuIds.php';

$app = new \PhpUssd\Core\Application(require __DIR__ . '/example/config/app.php');

echo $app->run($_POST ?: $_GET);
