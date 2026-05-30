<?php

declare(strict_types=1);

namespace App\Menus;

use PhpUssd\Core\UssdResponse;
use PhpUssd\Menu\AbstractMenu;

class CropAdviceMenu extends AbstractMenu
{
    private const CROPS = [
        '1' => 'maize',
        '2' => 'groundnut',
        '3' => 'tobacco',
    ];

    public function display(): UssdResponse
    {
        // If a crop was just selected, show its details then clear the session key.
        $selected = $this->session->get('selected_crop');
        if ($selected !== null) {
            $this->session->forget('selected_crop');
            return $this->respond(
                $this->t("{$selected}_advice") . "\n\n" . $this->t("{$selected}_advice_details")
            );
        }

        $error = $this->consumeError();
        $title = $error
            ? "{$error}\n\n{$this->t('crop_advice_title')}"
            : $this->t('crop_advice_title');

        return $this->formatMenu($title, [
            '1' => $this->t('maize_advice'),
            '2' => $this->t('groundnut_advice'),
            '3' => $this->t('tobacco_advice'),
        ]);
    }

    public function handleInput(): string|UssdResponse
    {
        $crop = self::CROPS[$this->lastInput] ?? null;

        if ($crop === null) {
            return $this->errorThen($this->t('invalid_input'), \MenuIds::CROP_ADVICE);
        }

        $this->session->set('selected_crop', $crop);
        return \MenuIds::CROP_ADVICE;
    }

    public function getParentMenu(): ?string
    {
        return \MenuIds::APP_OVERVIEW;
    }
}
