<?php

declare(strict_types=1);

namespace PhpUssd\Menu;

use PhpUssd\Core\UssdRequest;
use PhpUssd\Core\UssdResponse;
use PhpUssd\Http\HttpClient;
use PhpUssd\I18n\LanguageManager;
use PhpUssd\Session\SessionManagerInterface;

/**
 * Drives the menu state machine.
 *
 * Responsibilities:
 *   - Reads current menu from session
 *   - Handles "0" (back) and "00" (main menu) globally
 *   - Checks guards before display/input
 *   - Calls onEnter() when first entering a new menu
 *   - Maintains a history stack for back navigation
 *   - Detects language changes and reloads the language manager
 *   - Writes the new menu state back to session
 */
class MenuNavigator
{
    private string $currentMenuId;

    /** @var string[] */
    private array $history;

    public function __construct(
        private readonly MenuRouter             $router,
        private readonly SessionManagerInterface $session,
        private readonly LanguageManager        $lang,
        private readonly HttpClient             $http,
        private readonly string                 $defaultMenuId,
        private readonly string                 $mainMenuId,
    ) {
        $this->currentMenuId = $session->get('_current_menu') ?? $defaultMenuId;
        $this->history       = $session->get('_menu_history') ?? [];
    }

    /**
     * Process the request and return the response to send to the user.
     */
    public function handle(UssdRequest $request): UssdResponse
    {
        // Initial request — show default menu
        if ($request->isInitial()) {
            // Persist the default menu so the next request knows where we are
            if (!$this->session->has('_current_menu')) {
                $this->setCurrentMenu($this->defaultMenuId);
            }
            return $this->displayMenu($this->currentMenuId, $request);
        }

        // "00" — jump to main menu from anywhere
        if ($request->isMainMenu()) {
            $this->history = [];
            return $this->transitionTo($this->mainMenuId, $request);
        }

        // "0" — navigate back
        if ($request->isBack()) {
            return $this->handleBack($request);
        }

        // Normal input — process with current menu
        return $this->processInput($request);
    }

    // ── Private state machine methods ──────────────────────────────────────

    private function processInput(UssdRequest $request): UssdResponse
    {
        $menu = $this->router->resolve($this->currentMenuId, $request, $this->session, $this->lang, $this->http);

        // Run guards before processing input
        $guardResult = $this->checkGuards($menu, $request);
        if ($guardResult !== null) {
            return $guardResult;
        }

        $result = $menu->handleInput();

        // String result = transition to another menu
        if (is_string($result)) {
            return $this->transitionTo($result, $request);
        }

        // UssdResponse result = terminal or re-display
        return $result;
    }

    private function transitionTo(string $menuId, UssdRequest $request): UssdResponse
    {
        $previousMenuId = $this->currentMenuId;

        // Call onLeave on the previous menu (if different)
        if ($previousMenuId !== $menuId && $this->router->has($previousMenuId)) {
            $prev = $this->router->resolve($previousMenuId, $request, $this->session, $this->lang, $this->http);
            $prev->onLeave();
        }

        $this->setCurrentMenu($menuId);

        // Reload language if it changed since last request
        $sessionLang = $this->session->get('_language');
        if ($sessionLang && $sessionLang !== $this->lang->activeCode()) {
            $this->lang->setActive($sessionLang);
        }

        return $this->displayMenu($menuId, $request, isNewEntry: true);
    }

    private function displayMenu(string $menuId, UssdRequest $request, bool $isNewEntry = false): UssdResponse
    {
        $menu = $this->router->resolve($menuId, $request, $this->session, $this->lang, $this->http);

        // Run guards before display
        $guardResult = $this->checkGuards($menu, $request);
        if ($guardResult !== null) {
            return $guardResult;
        }

        if ($isNewEntry) {
            $menu->onEnter();
        }

        return $menu->display();
    }

    private function handleBack(UssdRequest $request): UssdResponse
    {
        $menu = $this->router->resolve($this->currentMenuId, $request, $this->session, $this->lang, $this->http);
        $parentId = $menu->getParentMenu();

        // No parent — stay on current menu (root menu)
        if ($parentId === null) {
            return $menu->display();
        }

        // Pop history
        if (!empty($this->history)) {
            array_pop($this->history);
            $this->session->set('_menu_history', $this->history);
        }

        return $this->transitionTo($parentId, $request);
    }

    private function checkGuards(AbstractMenu $menu, UssdRequest $request): ?UssdResponse
    {
        foreach ($menu->guards() as $guard) {
            if (is_string($guard)) {
                $guard = new $guard();
            }

            if (!$guard->passes($request, $this->session)) {
                $result = $guard->onFail($request, $this->session);

                if (is_string($result)) {
                    // Redirect to another menu
                    return $this->displayMenu($result, $request);
                }

                return $result;
            }
        }

        return null;
    }

    private function setCurrentMenu(string $menuId): void
    {
        $this->currentMenuId = $menuId;
        $this->session->set('_current_menu', $menuId);
        $this->history[] = $menuId;
        $this->session->set('_menu_history', $this->history);
    }
}
