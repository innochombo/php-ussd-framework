<?php

declare(strict_types=1);

namespace PhpUssd\Menu;

use PhpUssd\Core\UssdRequest;
use PhpUssd\Core\UssdResponse;
use PhpUssd\I18n\LanguageManager;
use PhpUssd\Session\SessionManagerInterface;
use PhpUssd\Http\HttpClient;

/**
 * Every menu in your application extends this class.
 *
 * The three abstract methods define the full contract:
 *
 *   display()        — build and return the screen the user sees
 *   handleInput()    — process last input; return next menu ID or a response
 *   getParentMenu()  — which menu the "0. Back" option navigates to
 *
 * Helpers available to all menus:
 *   $this->t('key')           — translate a key
 *   $this->tf('key', $a, $b) — translate with sprintf args
 *   $this->respond($body)     — build a CON UssdResponse
 *   $this->end($body)         — build an END UssdResponse
 *   $this->formatMenu(...)    — build a numbered option screen
 *   $this->redirectTo($id)    — return a menu ID (transition)
 *   $this->errorThen($id)     — store an error message, then redirect
 */
abstract class AbstractMenu
{
    protected UssdRequest            $request;
    protected SessionManagerInterface $session;
    protected LanguageManager        $lang;
    protected HttpClient             $http;

    // Convenience shortcut — same as $this->request->lastInput
    protected string $lastInput;

    /**
     * Called by the framework to inject dependencies.
     * Do not override this — use onEnter() for setup logic.
     */
    final public function boot(
        UssdRequest             $request,
        SessionManagerInterface $session,
        LanguageManager         $lang,
        HttpClient              $http,
    ): void {
        $this->request   = $request;
        $this->session   = $session;
        $this->lang      = $lang;
        $this->http      = $http;
        $this->lastInput = $request->lastInput;
    }

    // ── Required ──────────────────────────────────────────────────────────

    /**
     * Build the screen shown to the user.
     * Always return a UssdResponse — either respond() or end().
     */
    abstract public function display(): UssdResponse;

    /**
     * Process the user's last input.
     * Return a menu ID string to transition, or a UssdResponse for terminal states.
     */
    abstract public function handleInput(): string|UssdResponse;

    /**
     * The menu ID that "0. Back" navigates to.
     * Return null to indicate no back navigation (e.g. root/home menu).
     */
    abstract public function getParentMenu(): ?string;

    // ── Optional lifecycle hooks ───────────────────────────────────────────

    /**
     * Called once when navigating INTO this menu (not on re-display after error).
     * Use for fetching data and caching it in session.
     */
    public function onEnter(): void {}

    /**
     * Called when navigating AWAY from this menu.
     * Use for cleanup.
     */
    public function onLeave(): void {}

    /**
     * Guards that must pass before this menu is shown or handles input.
     * Return an array of class names or pre-constructed instances.
     *
     * @return array<class-string<MenuGuardInterface>|MenuGuardInterface>
     */
    public function guards(): array
    {
        return [];
    }

    // ── Response helpers ───────────────────────────────────────────────────

    /**
     * Build a "CON" (continue) response — user stays in the session.
     */
    protected function respond(string $body): UssdResponse
    {
        return UssdResponse::con($body);
    }

    /**
     * Build an "END" response — session terminates.
     */
    protected function end(string $body): UssdResponse
    {
        return UssdResponse::end($body);
    }

    /**
     * Build a numbered option screen.
     *
     * $options is a key → label array:
     *   ['1' => 'Wages', '2' => 'Tasks', '0' => 'Back']
     *
     * Result:
     *   CON My Title
     *   1. Wages
     *   2. Tasks
     *   0. Back
     */
    protected function formatMenu(string $title, array $options, bool $end = false): UssdResponse
    {
        $lines = [$title];
        foreach ($options as $key => $label) {
            $lines[] = "{$key}. {$label}";
        }
        $body = implode("\n", $lines);
        return $end ? UssdResponse::end($body) : UssdResponse::con($body);
    }

    /**
     * Navigation options appended to most menus.
     */
    protected function navOptions(bool $includeMainMenu = true): array
    {
        $opts = ['0' => $this->t('back')];
        if ($includeMainMenu) {
            $opts['00'] = $this->t('main_menu');
        }
        return $opts;
    }

    // ── Transition helpers ─────────────────────────────────────────────────

    /**
     * Transition to another menu by ID.
     * Syntactic sugar — identical to returning the menu ID directly,
     * but makes code more readable: return $this->redirectTo(MenuIds::WAGES);
     */
    protected function redirectTo(string $menuId): string
    {
        return $menuId;
    }

    /**
     * Store an error message for the current menu to display on re-render,
     * then return a menu ID (typically the same menu to re-show with the error).
     */
    protected function errorThen(string $message, string $menuId): string
    {
        $this->session->set('_error.' . static::class, $message);
        return $menuId;
    }

    /**
     * Retrieve and clear any pending error for this menu.
     */
    protected function consumeError(): ?string
    {
        $key   = '_error.' . static::class;
        $error = $this->session->get($key);
        if ($error !== null) {
            $this->session->forget($key);
        }
        return $error;
    }

    // ── I18n helpers ─────────────────────────────────────────────────────

    /** Translate a key. */
    protected function t(string $key): string
    {
        return $this->lang->get($key);
    }

    /** Translate a key with sprintf formatting. */
    protected function tf(string $key, mixed ...$args): string
    {
        return $this->lang->format($key, ...$args);
    }
}
