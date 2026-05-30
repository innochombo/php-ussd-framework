<?php

declare(strict_types=1);

namespace App\Menus;

use PhpUssd\Core\UssdResponse;
use PhpUssd\Menu\AbstractMenu;
use PhpUssd\Menu\MultiStepMenu;

/**
 * Two-step flow: pick a topic, then receive AI-generated advice for that topic.
 * Demonstrates MultiStepMenu: captureAndAdvance(), getStepValue(), clearSteps().
 */
class AiHelperMenu extends AbstractMenu
{
    use MultiStepMenu;

    protected function steps(): array
    {
        return ['select_topic', 'show_advice'];
    }

    public function display(): UssdResponse
    {
        return match ($this->currentStep()) {
            'select_topic' => $this->showTopics(),
            'show_advice'  => $this->showAdvice(),
            default        => $this->showTopics(),
        };
    }

    public function handleInput(): string|UssdResponse
    {
        if ($this->currentStep() !== 'select_topic') {
            return \MenuIds::APP_OVERVIEW;
        }

        if (!in_array($this->lastInput, ['1', '2', '3', '4'], true)) {
            return $this->errorThen($this->t('invalid_input'), \MenuIds::AI_HELPER);
        }

        $this->captureAndAdvance();
        return \MenuIds::AI_HELPER;
    }

    public function getParentMenu(): ?string
    {
        return \MenuIds::APP_OVERVIEW;
    }

    private function showTopics(): UssdResponse
    {
        $error = $this->consumeError();
        $title = $error
            ? "{$error}\n\n{$this->t('ai_helper_category')}"
            : $this->t('ai_helper_category');

        return $this->formatMenu($title, [
            '1' => $this->t('ai_category_crops'),
            '2' => $this->t('ai_category_weather'),
            '3' => $this->t('ai_category_market'),
            '4' => $this->t('ai_category_pests'),
        ]);
    }

    private function showAdvice(): UssdResponse
    {
        $topic = $this->getStepValue('select_topic');

        $adviceKey = match ($topic) {
            '1' => 'ai_helper_crops',
            '2' => 'ai_helper_weather',
            '3' => 'ai_helper_market',
            '4' => 'ai_helper_pests',
            default => 'ai_helper_response',
        };

        $this->clearSteps();
        return $this->end($this->t($adviceKey));
    }
}
