<?php

declare(strict_types=1);

namespace App\Menus;

use PhpUssd\Core\UssdResponse;
use PhpUssd\Menu\AbstractMenu;

class MarketPricesMenu extends AbstractMenu
{
    public function display(): UssdResponse
    {
        $body = $this->t('market_prices_title') . "\n"
              . $this->t('market_price_maize') . "\n"
              . $this->t('market_price_groundnut') . "\n"
              . $this->t('market_price_tobacco');

        return $this->formatMenu($body, $this->navOptions());
    }

    public function handleInput(): string|UssdResponse
    {
        return $this->errorThen($this->t('invalid_input'), \MenuIds::MARKET_PRICES);
    }

    public function getParentMenu(): ?string
    {
        return \MenuIds::APP_OVERVIEW;
    }
}
