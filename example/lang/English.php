<?php

declare(strict_types=1);

namespace App\Lang;

use PhpUssd\I18n\AbstractLanguage;

class English extends AbstractLanguage
{
    protected string $code = 'en';
    protected string $name = 'English';

    protected array $translations = [
        'welcome'                 => 'Welcome to the Farmer AI Helper.',
        'choose_language'         => 'Choose your language',
        'back'                    => 'Back',
        'main_menu'               => 'Main Menu',
        'invalid_input'           => 'Invalid input. Please try again.',
        'enter_phone_number'      => 'Enter your phone number',
        'invalid_phone_number'    => 'Enter a valid 9- or 10-digit phone number.',

        'app_overview'            => 'Farmer AI Helper',
        'crop_advice'             => 'Crop & Livestock Advice',
        'weather_tips'            => 'Weather Tips',
        'market_prices'           => 'Market Prices',
        'ai_helper'               => 'Ask the Farmer AI',

        'crop_advice_title'       => 'Choose a crop to get advice',
        'maize_advice'            => 'Maize growing tips',
        'groundnut_advice'        => 'Groundnut growing tips',
        'tobacco_advice'          => 'Tobacco growing tips',
        'maize_advice_details'    => 'Plant maize in well-drained soil, use certified seed, and apply fertilizer after emergence.',
        'groundnut_advice_details'=> 'Plant groundnuts in sandy loam soil, ensure good weed control, and harvest when pods are mature.',
        'tobacco_advice_details'  => 'Prepare a clean seedbed, use resistant varieties, and cure leaves carefully after harvest.',

        'weather_tips_title'      => 'Weather guidance',
        'weather_tip_rainy'       => 'Rainy season recommendations',
        'weather_tip_dry'         => 'Dry season recommendations',
        'weather_rainy_details'   => 'During the rainy season, plant early, prevent waterlogging, and check fields after storms.',
        'weather_dry_details'     => 'In dry weather, conserve moisture with mulch and water crops early in the morning.',

        'market_prices_title'     => 'Market prices per bag',
        'market_price_maize'      => 'Maize: MK 8,000',
        'market_price_groundnut'  => 'Groundnut: MK 12,000',
        'market_price_tobacco'    => 'Tobacco: MK 25,000',

        'ai_helper_category'      => 'What do you need help with?',
        'ai_helper_question'      => 'Type your question now',
        'ai_category_crops'       => 'Crops',
        'ai_category_weather'     => 'Weather',
        'ai_category_market'      => 'Markets',
        'ai_category_pests'       => 'Pests & disease',

        'ai_helper_crops'         => 'For crop advice, choose the right variety and maintain soil health through proper spacing and fertilizer.',
        'ai_helper_weather'       => 'Monitor forecasts, plant before heavy rains, and prepare drainage to protect your fields.',
        'ai_helper_market'        => 'Check local markets weekly, compare prices, and sell when demand is highest.',
        'ai_helper_pests'         => 'Rotate crops, scout regularly, and use natural pest controls to reduce damage.',
        'ai_helper_response'      => 'Use timely information and local knowledge to care for your farm. Visit the nearest extension officer if needed.',
    ];
}
