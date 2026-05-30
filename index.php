<?php

declare(strict_types=1);

/**
 * USSD Application Entry Point
 *
 * Before (original): ~20 lines of bootstrap + wiring
 * After  (framework): 3 lines.
 */

require_once __DIR__ . '/vendor/autoload.php';

// Load your app config's MenuIds so they are available globally
require_once __DIR__ . '/example/config/MenuIds.php';

$app = new \PhpUssd\Core\Application(require __DIR__ . '/example/config/app.php');

echo $app->run($_POST ?: $_GET);
