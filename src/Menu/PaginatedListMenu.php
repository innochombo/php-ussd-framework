<?php

declare(strict_types=1);

namespace PhpUssd\Menu;

use PhpUssd\Core\UssdResponse;

/**
 * Abstract base for menus that display a list of items with pagination.
 *
 * USSD screens are tiny (~182 characters on most networks).
 * Any list of N items needs pagination. This class handles:
 *   - Fetching + caching the item list in session
 *   - Slicing the correct page
 *   - Injecting "* Next" and "# Prev" navigation
 *   - Routing numeric inputs to onItemSelected()
 *
 * Subclass and implement three methods:
 *
 *   class AssignedTasksMenu extends PaginatedListMenu {
 *       protected function fetchItems(): array {
 *           return $this->http->get('/tasks/assigned')->get('data') ?? [];
 *       }
 *       protected function itemLabel(mixed $item): string {
 *           return $item['activity']['activityName'];
 *       }
 *       protected function onItemSelected(mixed $item): string|UssdResponse {
 *           $this->session->set('selected_task', $item);
 *           return MenuIds::TASK_DETAILS;
 *       }
 *       public function getParentMenu(): ?string { return MenuIds::TASKS; }
 *   }
 */
abstract class PaginatedListMenu extends AbstractMenu
{
    protected int    $pageSize  = 5;
    protected string $menuTitle = '';

    /**
     * Fetch the full list of items. Called once and cached in session.
     * Return an array of items (any shape — you define itemLabel and onItemSelected).
     */
    abstract protected function fetchItems(): array;

    /**
     * Return the display label for a single item.
     */
    abstract protected function itemLabel(mixed $item): string;

    /**
     * Called when the user selects an item.
     * Return a menu ID string or a UssdResponse.
     */
    abstract protected function onItemSelected(mixed $item): string|UssdResponse;

    /**
     * The title shown above the list. Override to customise.
     */
    protected function listTitle(): string
    {
        return $this->menuTitle !== '' ? $this->menuTitle : $this->t('items');
    }

    /**
     * Return text to show when the list is empty.
     */
    protected function emptyMessage(): string
    {
        return $this->t('no_items');
    }

    // ── AbstractMenu implementation ────────────────────────────────────────

    public function display(): UssdResponse
    {
        $items = $this->getCachedItems();
        $page  = $this->currentPage();

        if (empty($items)) {
            return $this->formatMenu(
                $this->listTitle(),
                array_merge(['' => $this->emptyMessage()], $this->navOptions()),
            );
        }

        $totalPages = (int) ceil(count($items) / $this->pageSize);
        $slice      = array_slice($items, $page * $this->pageSize, $this->pageSize);

        $options = [];
        foreach ($slice as $i => $item) {
            $options[(string)($i + 1)] = $this->itemLabel($item);
        }

        if ($page > 0) {
            $options['98'] = $this->t('prev_page');
        }
        if ($page < $totalPages - 1) {
            $options['99'] = $this->t('next_page');
        }

        $options['0']  = $this->t('back');
        $options['00'] = $this->t('main_menu');

        $error = $this->consumeError();
        $title = $error ? "{$error}\n\n{$this->listTitle()}" : $this->listTitle();

        return $this->formatMenu($title, $options);
    }

    public function handleInput(): string|UssdResponse
    {
        return match(true) {
            $this->lastInput === '99'    => $this->goToNextPage(),
            $this->lastInput === '98'    => $this->goToPrevPage(),
            $this->lastInput === '0'     => $this->getParentMenu() ?? MenuIds::HOME,
            is_numeric($this->lastInput) => $this->resolveSelection((int) $this->lastInput),
            default => $this->errorThen($this->t('invalid_input'), $this->currentMenuId()),
        };
    }

    // ── Pagination internals ───────────────────────────────────────────────

    private function getCachedItems(): array
    {
        $key = '_list.' . static::class;

        if (!$this->session->has($key)) {
            $items = $this->fetchItems();
            $this->session->set($key, $items);
        }

        return $this->session->get($key, []);
    }

    private function currentPage(): int
    {
        return (int) $this->session->get('_page.' . static::class, 0);
    }

    private function goToNextPage(): string
    {
        $key = '_page.' . static::class;
        $this->session->set($key, $this->currentPage() + 1);
        return $this->currentMenuId();
    }

    private function goToPrevPage(): string
    {
        $key = '_page.' . static::class;
        $this->session->set($key, max(0, $this->currentPage() - 1));
        return $this->currentMenuId();
    }

    private function resolveSelection(int $number): string|UssdResponse
    {
        $items  = $this->getCachedItems();
        $page   = $this->currentPage();
        $offset = $page * $this->pageSize;
        $index  = $offset + $number - 1;

        if (!isset($items[$index])) {
            return $this->errorThen($this->t('invalid_input'), $this->currentMenuId());
        }

        return $this->onItemSelected($items[$index]);
    }

    /**
     * Clears the cached items — call from onLeave() if the list may
     * have changed since the user entered this menu.
     */
    protected function invalidateCache(): void
    {
        $this->session->forget('_list.' . static::class);
        $this->session->forget('_page.' . static::class);
    }

    /**
     * The ID of this menu class. Override if your MenuIds constant
     * doesn't match the default derivation.
     */
    protected function currentMenuId(): string
    {
        // Subclasses should override or the NavigatOR will derive it from the registry
        return static::class;
    }
}
