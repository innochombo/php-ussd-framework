<?php

declare(strict_types=1);

namespace App\Menus;

use PhpUssd\Core\UssdResponse;
use PhpUssd\Menu\AbstractMenu;

class WeatherTipsMenu extends AbstractMenu
{
    private const SEASONS = [
        '1' => 'rainy',
        '2' => 'dry',
    ];

    public function display(): UssdResponse
    {
        $error = $this->consumeError();
        $title = $error
            ? "{$error}\n\n{$this->t('weather_tips_title')}"
            : $this->t('weather_tips_title');

        return $this->formatMenu($title, [
            '1' => $this->t('weather_tip_rainy'),
            '2' => $this->t('weather_tip_dry'),
        ]);
    }

    public function handleInput(): string|UssdResponse
    {
        $season = self::SEASONS[$this->lastInput] ?? null;

        if ($season === null) {
            return $this->errorThen($this->t('invalid_input'), \MenuIds::WEATHER_TIPS);
        }

        return $this->respond(
            $this->t("weather_tip_{$season}") . "\n\n" . $this->t("weather_{$season}_details")
        );
    }

    public function getParentMenu(): ?string
    {
        return \MenuIds::APP_OVERVIEW;
    }
}
