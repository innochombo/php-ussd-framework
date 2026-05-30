<?php

declare(strict_types=1);

namespace PhpUssd\Menu;

use PhpUssd\Core\UssdRequest;
use PhpUssd\Core\UssdResponse;
use PhpUssd\Exceptions\MenuNotFoundException;
use PhpUssd\Http\HttpClient;
use PhpUssd\I18n\LanguageManager;
use PhpUssd\Session\SessionManagerInterface;

/**
 * Maps menu ID strings → menu class names, and resolves instances on demand.
 *
 * The original code instantiated ALL 24 menus on every request. The router
 * only instantiates the single menu that is needed for the current request.
 */
class MenuRouter
{
    /** @var array<string, class-string<AbstractMenu>> */
    private array $registry = [];

    /** @var array<string, AbstractMenu> */
    private array $resolved = [];

    /**
     * @param array<string, class-string<AbstractMenu>> $menus  ['MENU_ID' => SomeMenu::class, ...]
     */
    public function __construct(array $menus = [])
    {
        foreach ($menus as $id => $class) {
            $this->register($id, $class);
        }
    }

    public function register(string $menuId, string $menuClass): void
    {
        if (!is_a($menuClass, AbstractMenu::class, true)) {
            throw new \InvalidArgumentException(
                "Menu class '{$menuClass}' must extend AbstractMenu."
            );
        }
        $this->registry[$menuId] = $menuClass;
    }

    /**
     * Resolve (and boot) a menu for the current request context.
     * A fresh instance is created each time — the router itself is
     * instantiated once per request in Application::run(), so there
     * is no cross-request leakage. Within a single request, if the same
     * menu ID is resolved twice (e.g. display then handleInput), we
     * return the same instance so state set in onEnter() persists.
     */
    public function resolve(
        string                  $menuId,
        UssdRequest             $request,
        SessionManagerInterface $session,
        LanguageManager         $lang,
        HttpClient              $http,
    ): AbstractMenu {
        if (isset($this->resolved[$menuId])) {
            return $this->resolved[$menuId];
        }

        if (!isset($this->registry[$menuId])) {
            throw MenuNotFoundException::forId($menuId);
        }

        $class = $this->registry[$menuId];

        /** @var AbstractMenu $instance */
        $instance = new $class();
        $instance->boot($request, $session, $lang, $http);

        $this->resolved[$menuId] = $instance;

        return $instance;
    }

    public function has(string $menuId): bool
    {
        return isset($this->registry[$menuId]);
    }

    /**
     * All registered menu IDs — useful for debugging.
     */
    public function registeredIds(): array
    {
        return array_keys($this->registry);
    }
}
