<?php

declare(strict_types=1);

/**
 * All menu ID constants in one place.
 *
 * Use these throughout your app instead of bare strings.
 * The framework treats these as opaque identifiers — the name
 * is for humans, the value is what gets stored in session.
 */
final class MenuIds
{
    // Language selection
    const LANGUAGE      = 'MENU_LANGUAGE';
    const PHONE         = 'MENU_PHONE';

    // Main farmer helper app
    const APP_OVERVIEW  = 'MENU_APP_OVERVIEW';
    const CROP_ADVICE   = 'MENU_CROP_ADVICE';
    const WEATHER_TIPS  = 'MENU_WEATHER_TIPS';
    const MARKET_PRICES = 'MENU_MARKET_PRICES';
    const AI_HELPER     = 'MENU_AI_HELPER';
}
