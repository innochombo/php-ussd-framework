<?php

declare(strict_types=1);

use PhpUssd\Gateway\AfricasTalkingDriver;
use PhpUssd\Gateway\JsonDriver;
use PhpUssd\Session\FileSessionManager;

// ── Menus ──────────────────────────────────────────────────────────────────
// Require the menu ID constants
require_once __DIR__ . '/MenuIds.php';

return [

    // ── Gateway ────────────────────────────────────────────────────────────
    // JsonDriver   — for the USSD Phone Simulator and API clients (JSON body/response)
    // AfricasTalkingDriver — for production AT gateway (form-encoded body, CON/END text)
    'gateway' => JsonDriver::class,

    // ── Session ────────────────────────────────────────────────────────────
    'session' => [
        'driver' => FileSessionManager::class,
        'path'   => __DIR__ . '/../storage/sessions',
        'ttl'    => 300,
    ],

    // ── Languages ──────────────────────────────────────────────────────────
    'default_language' => 'en',
    'languages' => [
        'en' => \App\Lang\English::class,
        'ny' => \App\Lang\Chichewa::class,
    ],

    // ── API ────────────────────────────────────────────────────────────────
    'api' => [
        'base_url'      => env('API_BASE_URL', 'http://localhost:5000/api/v1'),
        'timeout'       => 30,
        'retries'       => 1,
        'throw_on_error' => false,
        'headers'       => [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ],
    ],

    // ── Middleware ───────────────────────────────────────────────────────────
    'middleware' => [
        [
            'class' => \PhpUssd\Http\CorsMiddleware::class,
            'options' => [
                'allow_origins'   => [
                    'https://ussd-phone-simulator.vercel.app', // hosted simulator
                    'http://localhost:5173',                    // local dev
                ],
                'allow_methods'   => ['GET', 'POST', 'OPTIONS'],
                'allow_headers'   => ['Content-Type', 'X-Requested-With'],
                'allow_credentials' => false,
                'max_age'         => 600,
            ],
        ],
    ],

    // ── Navigation ─────────────────────────────────────────────────────────
    'default_menu' => MenuIds::LANGUAGE,
    'main_menu'    => MenuIds::APP_OVERVIEW,

    // ── Menus registry ─────────────────────────────────────────────────────
    // This is the ONLY place you register menus.
    // The framework lazy-instantiates — only the needed menu is created per request.
    'menus' => [
        MenuIds::LANGUAGE       => \App\Menus\LanguageMenu::class,
        MenuIds::PHONE          => \App\Menus\WorkerIdMenu::class,
        MenuIds::APP_OVERVIEW   => \App\Menus\AppOverviewMenu::class,
        MenuIds::CROP_ADVICE    => \App\Menus\CropAdviceMenu::class,
        MenuIds::WEATHER_TIPS   => \App\Menus\WeatherTipsMenu::class,
        MenuIds::MARKET_PRICES  => \App\Menus\MarketPricesMenu::class,
        MenuIds::AI_HELPER      => \App\Menus\AiHelperMenu::class,
    ],
];
