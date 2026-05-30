<?php

declare(strict_types=1);

namespace App\Menus;

use PhpUssd\Core\UssdResponse;
use PhpUssd\Menu\AbstractMenu;

class LanguageMenu extends AbstractMenu
{
    public function display(): UssdResponse
    {
        $languages = $this->lang->availableLanguages();
        $options   = [];
        $i         = 1;
        foreach ($languages as $code => $name) {
            $options[(string)$i++] = $name;
        }

        return $this->formatMenu(
            $this->t('welcome') . "\n" . $this->t('choose_language'),
            $options,
        );
    }

    public function handleInput(): string|UssdResponse
    {
        $languages = array_keys($this->lang->availableLanguages());
        $index     = (int)$this->lastInput - 1;

        if (!isset($languages[$index])) {
            return $this->errorThen($this->t('invalid_input'), \MenuIds::LANGUAGE);
        }

        $selectedCode = $languages[$index];
        $this->lang->setActive($selectedCode);
        $this->session->set('_language', $selectedCode);

        return \MenuIds::PHONE;
    }

    public function getParentMenu(): ?string
    {
        return null; // Root — no back navigation
    }
}
