<?php

declare(strict_types=1);

use PhpUssd\Gateway\AfricasTalkingDriver;
use PhpUssd\Session\FileSessionManager;

// ── Menus ──────────────────────────────────────────────────────────────────
// Require the menu ID constants
require_once __DIR__ . '/MenuIds.php';

return [

    // ── Gateway ────────────────────────────────────────────────────────────
    'gateway' => AfricasTalkingDriver::class,

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
