<?php

declare(strict_types=1);

namespace PhpUssd\Menu;

/**
 * Adds step-based flow management to any AbstractMenu.
 *
 * Usage:
 *
 *   class ChangePinMenu extends AbstractMenu {
 *       use MultiStepMenu;
 *
 *       protected function steps(): array {
 *           return ['enter_old', 'enter_new', 'confirm'];
 *       }
 *
 *       public function display(): UssdResponse {
 *           return match($this->currentStep()) {
 *               'enter_old' => $this->respond($this->t('enter_old_pin')),
 *               'enter_new' => $this->respond($this->t('enter_new_pin')),
 *               'confirm'   => $this->respond($this->t('confirm_new_pin')),
 *           };
 *       }
 *
 *       public function handleInput(): string|UssdResponse {
 *           // ... store input, advance step, or finish
 *           $this->storeStep($this->lastInput);
 *           $this->advanceStep();
 *           return SomeMenuIds::CHANGE_PIN; // re-display, next step shown
 *       }
 *   }
 *
 * Each menu's step data is namespaced to that class, so two different
 * multi-step menus running in the same session never collide.
 */
trait MultiStepMenu
{
    /**
     * Define the ordered list of step identifiers.
     * Must be implemented by the class using this trait.
     *
     * @return string[]
     */
    abstract protected function steps(): array;

    /**
     * The session key namespace for this menu's step data.
     * Isolated per class so two multi-step menus never conflict.
     */
    private function stepNamespace(): string
    {
        return '_steps.' . static::class;
    }

    /**
     * Returns the identifier of the current step.
     */
    protected function currentStep(): string
    {
        return $this->session->get($this->stepNamespace() . '.current') ?? $this->steps()[0];
    }

    /**
     * Advance to the next step. Call after storing the current step's input.
     * Does nothing if already on the last step.
     */
    protected function advanceStep(): void
    {
        $steps = $this->steps();
        $idx   = array_search($this->currentStep(), $steps, true);

        if ($idx !== false && isset($steps[$idx + 1])) {
            $this->session->set($this->stepNamespace() . '.current', $steps[$idx + 1]);
        }
    }

    /**
     * Go back one step.
     */
    protected function rewindStep(): void
    {
        $steps = $this->steps();
        $idx   = array_search($this->currentStep(), $steps, true);

        if ($idx !== false && $idx > 0) {
            $this->session->set($this->stepNamespace() . '.current', $steps[$idx - 1]);
        }
    }

    /**
     * Store the user's input for the current step.
     * Retrieve later with getStepValue('step_name').
     */
    protected function storeStepValue(string $stepName, mixed $value): void
    {
        $this->session->set($this->stepNamespace() . '.data.' . $stepName, $value);
    }

    /**
     * Shorthand: store lastInput for the current step and advance.
     */
    protected function captureAndAdvance(): void
    {
        $this->storeStepValue($this->currentStep(), $this->lastInput);
        $this->advanceStep();
    }

    /**
     * Get the stored value for a given step.
     */
    protected function getStepValue(string $stepName, mixed $default = null): mixed
    {
        return $this->session->get($this->stepNamespace() . '.data.' . $stepName, $default);
    }

    /**
     * True when the current step is the last one in the list.
     */
    protected function isLastStep(): bool
    {
        $steps = $this->steps();
        return $this->currentStep() === end($steps);
    }

    /**
     * True when the current step is the first one in the list.
     */
    protected function isFirstStep(): bool
    {
        return $this->currentStep() === $this->steps()[0];
    }

    /**
     * Clear all step data for this menu (call after successful completion).
     */
    protected function clearSteps(): void
    {
        $this->session->forget($this->stepNamespace());
    }

    /**
     * Reset to the first step without clearing stored values.
     */
    protected function resetToFirstStep(): void
    {
        $this->session->set($this->stepNamespace() . '.current', $this->steps()[0]);
    }
}
