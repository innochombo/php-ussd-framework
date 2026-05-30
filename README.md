# PhpUSSD

A fast, minimal PHP framework for building state-machine-based USSD applications.

PHP 8.1+ · No dependencies · 75 tests passing

---

## Documentation

- `docs/framework-philosophy.md` — architecture, design principles, and framework philosophy.
- `docs/guidelines-usage.md` — practical usage patterns, menu design, and developer guidance.
- `docs/contributing-and-prompts.md` — contribution guidance and AI prompt best practices.

## Installation

```bash
composer require phpussd/phpussd
```

---

## Quick start

### 1. Create `index.php`

```php
<?php
require 'vendor/autoload.php';
require 'config/MenuIds.php';

$app = new \PhpUssd\Core\Application(require 'config/app.php');
echo $app->run($_POST);
```

### 2. Create `config/app.php`

```php
<?php
use PhpUssd\Gateway\AfricasTalkingDriver;
use PhpUssd\Session\FileSessionManager;

return [
    'gateway'          => AfricasTalkingDriver::class,
    'default_language' => 'en',
    'languages'        => ['en' => App\Lang\English::class],
    'default_menu'     => MenuIds::LANGUAGE,
    'main_menu'        => MenuIds::HOME,
    'session'          => [
        'driver' => FileSessionManager::class,
        'path'   => __DIR__ . '/../storage/sessions',
        'ttl'    => 300,
    ],
    'api'              => [
        'base_url' => 'https://api.yourapp.com/v1',
        'timeout'  => 30,
        'retries'  => 1,
    ],
    'menus'            => [
        MenuIds::LANGUAGE => App\Menus\LanguageMenu::class,
        MenuIds::HOME     => App\Menus\HomeMenu::class,
        // ... all your menus
    ],
];
```

### 3. Write a menu

```php
<?php
namespace App\Menus;

use PhpUssd\Core\UssdResponse;
use PhpUssd\Menu\AbstractMenu;

class HomeMenu extends AbstractMenu
{
    public function display(): UssdResponse
    {
        return $this->formatMenu($this->t('home_title'), [
            '1' => $this->t('wages'),
            '2' => $this->t('account'),
            '0' => $this->t('back'),
        ]);
    }

    public function handleInput(): string|UssdResponse
    {
        return match($this->lastInput) {
            '1' => MenuIds::WAGES,
            '2' => MenuIds::ACCOUNT,
            default => $this->errorThen($this->t('invalid_input'), MenuIds::HOME),
        };
    }

    public function getParentMenu(): ?string
    {
        return null; // Root menu — no back navigation
    }
}
```

---

## Core concepts

### `AbstractMenu`

Every menu extends `AbstractMenu` and implements three methods:

| Method | Purpose |
|---|---|
| `display(): UssdResponse` | Build the screen shown to the user |
| `handleInput(): string\|UssdResponse` | Process input; return a menu ID (transition) or response |
| `getParentMenu(): ?string` | Target for "0. Back"; `null` = no back |

**Available in every menu:**

```php
$this->lastInput        // The user's current input
$this->session          // SessionManagerInterface
$this->lang             // LanguageManager
$this->http             // HttpClient

$this->t('key')         // Translate a key
$this->tf('key', $arg)  // Translate with sprintf
$this->respond($body)   // Build CON response
$this->end($body)       // Build END response
$this->formatMenu($title, $options)  // Build a numbered menu screen
$this->errorThen($msg, $menuId)      // Store error, return menu ID
$this->consumeError()                // Get and clear pending error
$this->navOptions()                  // ['0' => 'Back', '00' => 'Main Menu']
```

### `MultiStepMenu` trait

Eliminates the manual `$step = $session->get('x_step')` copy-paste for flows with multiple input stages:

```php
class ChangePinMenu extends AbstractMenu
{
    use MultiStepMenu;

    protected function steps(): array
    {
        return ['enter_old', 'enter_new', 'confirm'];
    }

    public function display(): UssdResponse
    {
        return match($this->currentStep()) {
            'enter_old' => $this->respond($this->t('enter_old_pin')),
            'enter_new' => $this->respond($this->t('enter_new_pin')),
            'confirm'   => $this->respond($this->t('confirm_new_pin')),
        };
    }

    public function handleInput(): string|UssdResponse
    {
        $this->captureAndAdvance(); // stores lastInput for current step, advances
        if ($this->isLastStep()) {
            return $this->finish();
        }
        return MenuIds::CHANGE_PIN;
    }
}
```

**Trait methods:** `currentStep()`, `advanceStep()`, `rewindStep()`, `captureAndAdvance()`, `storeStepValue(step, value)`, `getStepValue(step)`, `isFirstStep()`, `isLastStep()`, `clearSteps()`, `resetToFirstStep()`.

Step data is **namespaced per class** — two multi-step menus in the same session never overwrite each other.

### `PaginatedListMenu`

For any list that might exceed one USSD screen. Handles fetch, cache, pagination, and item selection:

```php
class AssignedTasksMenu extends PaginatedListMenu
{
    protected function fetchItems(): array
    {
        return $this->http->get('/tasks/assigned')->get('') ?? [];
    }

    protected function itemLabel(mixed $item): string
    {
        return $item['activity']['activityName'];
    }

    protected function onItemSelected(mixed $item): string|UssdResponse
    {
        $this->session->set('selected_task', $item);
        return MenuIds::TASK_DETAILS;
    }

    protected function listTitle(): string { return $this->t('assigned_tasks'); }
    protected function currentMenuId(): string { return MenuIds::ASSIGNED_TASKS; }
    public function getParentMenu(): ?string { return MenuIds::TASKS; }
}
```

Pagination inputs: `99` = next page, `98` = previous page (avoids conflict with Africa's Talking's `*` delimiter).

### Guards

Protect menus without putting auth logic inside them:

```php
class AuthGuard implements MenuGuardInterface
{
    public function passes(UssdRequest $request, SessionManagerInterface $session): bool
    {
        return $session->has('auth_token');
    }

    public function onFail(UssdRequest $request, SessionManagerInterface $session): string|UssdResponse
    {
        return MenuIds::LOGIN; // Redirect
    }
}

// Apply to a menu:
class WagesMenu extends AbstractMenu
{
    public function guards(): array { return [AuthGuard::class]; }
    // ...
}
```

---

## Session drivers

| Driver | Use case |
|---|---|
| `FileSessionManager` | Development, low-traffic deployments |
| `RedisSessionManager` | Production (requires `ext-redis`) |
| `ArraySessionManager` | Unit tests |

Session writes are **batched** — accumulated in memory and flushed once at the end of the request via `save()`. No per-`set()` disk writes.

---

## Gateway drivers

| Driver | Gateway |
|---|---|
| `AfricasTalkingDriver` | Africa's Talking |
| `NaloDriver` | Nalo Solutions |

Implement `GatewayDriverInterface` to add your own.

---

## HTTP client

```php
$response = $this->http->get('/users/123');
$response = $this->http->post('/auth/login', ['pin' => '1234']);
$response = $this->http->put('/accounts/456', ['name' => 'Alice']);

$response->ok()          // true for 2xx
$response->failed()      // true for 4xx/5xx or network error
$response->get('a.b.c') // dot-notation access to JSON data
$response->hasErrors()   // true if response has 'errors' key
$response->data          // raw decoded array
```

The client supports retries with exponential back-off. Configure via `api.retries` in app config.

---

## Testing

```bash
php run_tests.php
```

Use `ArraySessionManager` in your own tests:

```php
ArraySessionManager::flush(); // Clean slate between tests

$session = new ArraySessionManager();
$session->load('test_session_id');

$menu = new MyMenu();
$menu->boot(
    new UssdRequest('sess_id', '+265880000001', '1'),
    $session,
    new LanguageManager(['en' => English::class], 'en'),
    new HttpClient()
);

$response = $menu->handleInput();
$this->assertEquals(MenuIds::NEXT_MENU, $response);
```

---

## Directory structure

```
src/
  Core/
    Application.php       ← Bootstrap and entry point
    Config.php            ← Dot-notation config wrapper
    Container.php         ← Minimal DI container
    UssdRequest.php       ← Immutable request value object
    UssdResponse.php      ← Immutable CON/END response
  Exceptions/
    UssdException.php
    MenuNotFoundException.php
    SessionException.php
    ConfigurationException.php
  Gateway/
    GatewayDriverInterface.php
    AfricasTalkingDriver.php
    NaloDriver.php
  Http/
    HttpClient.php        ← cURL wrapper with retry
    HttpResponse.php      ← Typed response with dot-notation
  I18n/
    LanguageInterface.php
    AbstractLanguage.php  ← Array-backed translation base
    LanguageManager.php   ← Active language + lazy loading
  Menu/
    AbstractMenu.php      ← Base class every menu extends
    MultiStepMenu.php     ← Trait for step-based flows
    PaginatedListMenu.php ← Abstract base for paginated lists
    MenuGuardInterface.php
    MenuNavigator.php     ← State machine driver
    MenuRouter.php        ← ID → class mapping, lazy instantiation
  Session/
    SessionManagerInterface.php
    AbstractSessionManager.php ← Dot-notation get/set base
    FileSessionManager.php
    RedisSessionManager.php
    ArraySessionManager.php    ← For tests
```
