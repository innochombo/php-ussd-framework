<?php

declare(strict_types=1);

namespace App\Menus;

use PhpUssd\Core\UssdResponse;
use PhpUssd\Menu\AbstractMenu;

class AppOverviewMenu extends AbstractMenu
{
    public function display(): UssdResponse
    {
        $error = $this->consumeError();
        $title = $error ? "{$error}\n\n{$this->t('app_overview')}" : $this->t('app_overview');

        return $this->formatMenu($title, [
            '1' => $this->t('crop_advice'),
            '2' => $this->t('weather_tips'),
            '3' => $this->t('market_prices'),
            '4' => $this->t('ai_helper'),
        ]);
    }

    public function handleInput(): string|UssdResponse
    {
        return match ($this->lastInput) {
            '1' => \MenuIds::CROP_ADVICE,
            '2' => \MenuIds::WEATHER_TIPS,
            '3' => \MenuIds::MARKET_PRICES,
            '4' => \MenuIds::AI_HELPER,
            default => $this->errorThen($this->t('invalid_input'), \MenuIds::APP_OVERVIEW),
        };
    }

    public function getParentMenu(): ?string
    {
        return null;
    }
}
