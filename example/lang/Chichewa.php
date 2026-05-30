<?php

declare(strict_types=1);

namespace App\Lang;

use PhpUssd\I18n\AbstractLanguage;

class Chichewa extends AbstractLanguage
{
    protected string $code = 'ny';
    protected string $name = 'Chichewa';

    protected array $translations = [
        // Common navigation
        'welcome'              => 'Takulandirani ku Farmer AI Helper.',
        'choose_language'      => 'Sankhani chiyankhulo chanu',
        'back'                 => 'Bwelera',
        'main_menu'            => 'Menyu Yoyamba',
        'invalid_input'        => 'Mwalowetsa zolakwika. Chonde yesaninso.',

        // Phone entry
        'enter_phone_number'   => 'Lowetsani nambala yanu ya foni',
        'invalid_phone_number' => 'Lowetsani nambala yabwino ya manambala 9 kapena 10.',

        // App overview
        'app_overview'         => 'Farmer AI Helper',
        'crop_advice'          => 'Malangizo a Mbewu & Ziweto',
        'weather_tips'         => 'Malangizo a Nyengo',
        'market_prices'        => 'Mitengo ya Msika',
        'ai_helper'            => 'Funsani Farmer AI',

        // Crop advice
        'crop_advice_title'    => 'Sankhani mbewu kuti mupeze malangizo',
        'maize_advice'         => 'Malangizo a chimanga',
        'groundnut_advice'     => 'Malangizo a mtedza',
        'tobacco_advice'       => 'Malangizo a fodya',

        'maize_advice_details'    => 'Byalani chimanga m\'nthaka youma madzi bwino, gwiritsani ntchito mbewu zovomerezeka, ndi kupeleka feteleza pambuyo pa kutha.',
        'groundnut_advice_details'=> 'Byalani mtedza m\'nthaka ya mchenga wa loam, sungani bwino kulephera ma widi, ndi kumwetula pomwe ma pod akwanira.',
        'tobacco_advice_details'  => 'Konzani mbewu zosavuta, gwiritsani ntchito mitundu yokhangana ndi matenda, ndi kusindikiza masamba molongosoka pambuyo pa kukola.',

        // Weather tips
        'weather_tips_title'   => 'Malangizo a nyengo',
        'weather_tip_rainy'    => 'Malangizo a nyengo ya mvula',
        'weather_tip_dry'      => 'Malangizo a nyengo yowuma',

        'weather_rainy_details' => 'Nyengo ya mvula: byalani msanga, pewhani madzi akupita pansi, ndi kuyang\'anira minda pambuyo pa mvula yakulukululu.',
        'weather_dry_details'   => 'Nyengo yowuma: sungani madzi ndi matete a pa nthaka, ndi kuthirira zomera m\'mawa mwake.',

        // Market prices
        'market_prices_title'   => 'Mitengo ya msika pa begi',
        'market_price_maize'    => 'Chimanga: MK 8,000',
        'market_price_groundnut'=> 'Mtedza: MK 12,000',
        'market_price_tobacco'  => 'Fodya: MK 25,000',

        // AI helper
        'ai_helper_category'   => 'Mukufuna chithandizo cha chiyani?',
        'ai_helper_question'   => 'Lowetsani funso lanu tsopano',
        'ai_category_crops'    => 'Mbewu',
        'ai_category_weather'  => 'Nyengo',
        'ai_category_market'   => 'Msika',
        'ai_category_pests'    => 'Zitsiru & Matenda',

        'ai_helper_crops'    => 'Sankhani mtundu wabwino wa mbewu ndi kusunga thanzi la nthaka kudzera mu kulimba bwino ndi feteleza.',
        'ai_helper_weather'  => 'Yang\'anirani zakuchitika kwa nyengo, byalani mvula isanagwe kwambiri, ndi kukonza njira za madzi kuteteza minda yanu.',
        'ai_helper_market'   => 'Onetsani mitengo ya msika kulikonse sabata, yerekezani mitengo, ndi kugulitsa pomwe zofunikira zamomba.',
        'ai_helper_pests'    => 'Singirani mbewu, yang\'anirani nthaka nthawi zonse, ndi kugwiritsa ntchito njira zachilengedwe kuchepetsa kuwonongeka.',
        'ai_helper_response' => 'Gwiritsani ntchito chidziwitso ndi chidziwitso cha mdera kosamalira malo anu olima. Pitani kwa ofotokoza a malonda akuya ngati mufunikira.',
    ];
}
