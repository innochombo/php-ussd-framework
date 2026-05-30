<?php

declare(strict_types=1);

namespace App\Menus;

use PhpUssd\Core\UssdResponse;
use PhpUssd\Menu\AbstractMenu;

class WorkerIdMenu extends AbstractMenu
{
    public function display(): UssdResponse
    {
        $error = $this->consumeError();
        $title = $error ? "{$error}\n\n{$this->t('enter_phone_number')}" : $this->t('enter_phone_number');
        return $this->formatMenu($title, ['0' => $this->t('back')]);
    }

    public function handleInput(): string|UssdResponse
    {
        if (!preg_match('/^\d{9,10}$/', $this->lastInput)) {
            return $this->errorThen($this->t('invalid_phone_number'), \MenuIds::PHONE);
        }

        $this->session->set('phone_number', $this->lastInput);
        return \MenuIds::APP_OVERVIEW;
    }

    public function getParentMenu(): ?string
    {
        return \MenuIds::LANGUAGE;
    }
}
