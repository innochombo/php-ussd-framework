# PhpUSSD

A fast, minimal PHP framework for building state-machine-based USSD applications.

**PHP 8.1+ · Zero runtime dependencies · 75 tests passing**

---

## Features

### Core

| Feature | Description |
|---|---|
| **State machine navigation** | Every screen is a menu node. Every input is an explicit transition. No hidden routing — the full application flow is readable from one config file. |
| **Lazy menu instantiation** | Only the single menu needed for the current request is constructed. All others are registered by class name and never loaded. |
| **Batched session writes** | Session mutations accumulate in memory and flush once at the end of the request. No per-`set()` disk or Redis I/O. |
| **Back / Main / Paginate** | `0` (back), `00` (main menu), `99` (next page), and `98` (prev page) are handled by the navigator before `handleInput()` is called. Menus never implement these themselves. |
| **Zero dependencies** | The framework `require`s only `php: >=8.1`. Nothing else in Composer. Safe to drop into any PHP environment. |

### Menu Abstractions

| Feature | Description |
|---|---|
| **`AbstractMenu`** | Base class for every screen. Implements `display()`, `handleInput()`, and `getParentMenu()`. Injects session, language, and HTTP client automatically. |
| **`MultiStepMenu` trait** | Eliminates manual step-counter boilerplate for multi-input flows. Declare step names; the trait manages position, storage, and advancement — namespaced per class so two flows never overwrite each other. |
| **`PaginatedListMenu`** | Base class for lists that exceed one USSD screen. Handles fetch, cache, page boundaries, and `98`/`99` navigation. Subclass implements `fetchItems()`, `itemLabel()`, and `onItemSelected()`. |
| **Guards** | Implement `MenuGuardInterface` to protect menus without embedding auth logic inside them. Guards run before both `display()` and `handleInput()`. Multiple guards can be stacked. |
| **Lifecycle hooks** | Optional `onEnter()` and `onLeave()` methods on every menu. Use `onEnter()` to pre-fetch data into session; `onLeave()` to clean up. |
| **Error pattern** | `errorThen($message, $menuId)` stores an error in session and returns a menu ID. The next `display()` call reads it with `consumeError()` and prepends it to the screen. |

### Gateway Drivers

| Driver | Format | When to use |
|---|---|---|
| `AfricasTalkingDriver` | Form-encoded POST · plain text `CON …`/`END …` | Africa's Talking production gateway |
| `NaloDriver` | Form-encoded POST · plain text | Nalo Solutions gateway |
| `JsonDriver` | JSON POST · JSON `{"type","message","sessionId"}` | USSD Phone Simulator, REST clients, local dev |
| Custom | Any | Implement `GatewayDriverInterface` |

### Session Drivers

| Driver | Backing store | When to use |
|---|---|---|
| `FileSessionManager` | JSON files on disk | Development, low-traffic deployments |
| `RedisSessionManager` | Redis (`ext-redis` required) | Production, multi-server |
| `ArraySessionManager` | In-memory PHP array | Unit and integration tests |

### Internationalisation

| Feature | Description |
|---|---|
| **Multi-language** | Register any number of language providers in config. Active language stored in session and restored on every request automatically. |
| **`AbstractLanguage`** | Array-backed translation provider. Extend it with a `$translations` array — no config files, no parsing. |
| **Missing key visibility** | Missing translation keys render as `[missing:key_name]` rather than empty strings — immediately visible during development. |
| **`sprintf` formatting** | `$this->tf('key', $arg1, $arg2)` wraps `sprintf` for parameterised translations. |

### HTTP Client

| Feature | Description |
|---|---|
| **Pre-configured** | Injected as `$this->http` in every menu. Base URL, timeout, and default headers set once in config. |
| **Retry with back-off** | Retries failed requests using exponential back-off. Retry count is configurable. |
| **Dot-notation responses** | `$response->get('user.profile.name')` for nested JSON access without manual array traversal. |
| **Auth helpers** | `$this->http->withToken($token)` and `$this->http->withHeaders([...])` for per-request overrides. |

### Middleware

| Feature | Description |
|---|---|
| **Pipeline** | Middleware wraps every request. Declared in `app.php`, runs outermost-first. Supports class name, `{class, options}` array, or factory callable. |
| **`CorsMiddleware`** | Built-in. Sends `Access-Control-Allow-*` headers and short-circuits `OPTIONS` preflights. Fully configurable per-origin allowlist, methods, headers, credentials, and `max_age`. |
| **Custom middleware** | Implement `MiddlewareInterface` — one method: `process(array $payload, callable $next): string`. Call `$next($payload)` to pass through or return directly to short-circuit. |

### Simulator Integration

| Feature | Description |
|---|---|
| **`JsonDriver`** | Speaks the same protocol as the [USSD Phone Simulator](https://ussd-phone-simulator.vercel.app/) — accepts `{"sessionId","serviceCode","msisdn","input"}`, returns `{"type","message","sessionId"}`. |
| **JSON body parsing** | `index.php` detects `Content-Type: application/json` and reads from `php://input` automatically. No changes to menus needed. |
| **CORS via middleware** | Configure `CorsMiddleware` in `app.php` to allow the simulator origin. No CORS logic needed in `index.php`. |

---

## Installation

```bash
composer require phpussd/phpussd
```

---

## Quick Start

### 1. `index.php`

```php
<?php
require 'vendor/autoload.php';
require 'config/MenuIds.php';

$app = new \PhpUssd\Core\Application(require 'config/app.php');

// PHP only auto-populates $_POST for form-encoded bodies.
// JSON bodies (USSD simulator, REST clients) must be read from php://input.
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $payload = ($raw !== false && $raw !== '') ? (json_decode($raw, true) ?? []) : [];
} else {
    $payload = $_POST ?: $_GET;
}

echo $app->run($payload);
```

### 2. `config/app.php`

```php
<?php
use PhpUssd\Gateway\AfricasTalkingDriver;
use PhpUssd\Gateway\JsonDriver;
use PhpUssd\Session\FileSessionManager;

return [
    // JsonDriver           → USSD simulator / local dev (JSON body, JSON response)
    // AfricasTalkingDriver → Africa's Talking production (form-encoded, CON/END text)
    'gateway' => JsonDriver::class,

    'default_language' => 'en',
    'languages'        => ['en' => App\Lang\English::class],

    'session' => [
        'driver' => FileSessionManager::class,
        'path'   => __DIR__ . '/../storage/sessions',
        'ttl'    => 300,
    ],

    'api' => [
        'base_url' => 'https://api.yourapp.com/v1',
        'timeout'  => 30,
        'retries'  => 1,
    ],

    'default_menu' => MenuIds::LANGUAGE,
    'main_menu'    => MenuIds::HOME,

    'menus' => [
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
        return match ($this->lastInput) {
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

## Core Concepts

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

$this->t('key')                      // Translate a key
$this->tf('key', $arg)               // Translate with sprintf
$this->respond($body)                // Build CON response
$this->end($body)                    // Build END response
$this->formatMenu($title, $options)  // Numbered menu screen
$this->errorThen($msg, $menuId)      // Store error, return menu ID
$this->consumeError()                // Get and clear pending error
$this->navOptions()                  // ['0' => 'Back', '00' => 'Main Menu']
```

### `MultiStepMenu` Trait

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
        return match ($this->currentStep()) {
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

## Session Drivers

| Driver | Use case |
|---|---|
| `FileSessionManager` | Development, low-traffic deployments |
| `RedisSessionManager` | Production (requires `ext-redis`) |
| `ArraySessionManager` | Unit tests |

Session writes are **batched** — accumulated in memory and flushed once at the end of the request via `save()`. No per-`set()` disk writes.

---

## Gateway Drivers

| Driver | Format | When to use |
|---|---|---|
| `AfricasTalkingDriver` | Form-encoded · plain text `CON/END` | Africa's Talking production |
| `NaloDriver` | Form-encoded · plain text `CON/END` | Nalo Solutions production |
| `JsonDriver` | JSON body · JSON response | Simulator, REST clients, local dev |

Implement `GatewayDriverInterface` to add your own.

---

## HTTP Client

```php
$response = $this->http->get('/users/123');
$response = $this->http->post('/auth/login', ['pin' => '1234']);
$response = $this->http->put('/accounts/456', ['name' => 'Alice']);

$response->ok()           // true for 2xx
$response->failed()       // true for 4xx/5xx or network error
$response->get('a.b.c')  // dot-notation access to JSON data
$response->hasErrors()    // true if response has 'errors' key
$response->data           // raw decoded array
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

## Directory Structure

```
src/
  Core/
    Application.php            ← Bootstrap kernel and entry point
    Config.php                 ← Dot-notation config wrapper
    Container.php              ← Minimal DI container
    UssdRequest.php            ← Immutable request value object
    UssdResponse.php           ← Immutable CON/END response
  Exceptions/
    UssdException.php
    MenuNotFoundException.php
    SessionException.php
    ConfigurationException.php
  Gateway/
    GatewayDriverInterface.php
    AfricasTalkingDriver.php   ← Africa's Talking (form-encoded, plain text)
    NaloDriver.php             ← Nalo Solutions
    JsonDriver.php             ← Simulator & REST clients (JSON body/response)
  Http/
    HttpClient.php             ← cURL wrapper with retry + back-off
    HttpResponse.php           ← Typed response with dot-notation access
  I18n/
    LanguageInterface.php
    AbstractLanguage.php       ← Array-backed translation base
    LanguageManager.php        ← Active language + lazy loading
  Menu/
    AbstractMenu.php           ← Base class every menu extends
    MultiStepMenu.php          ← Trait: step-based multi-input flows
    PaginatedListMenu.php      ← Abstract base for paginated list screens
    MenuGuardInterface.php
    MenuNavigator.php          ← State machine driver
    MenuRouter.php             ← ID → class mapping, lazy instantiation
  Session/
    SessionManagerInterface.php
    AbstractSessionManager.php ← Dot-notation get/set base
    FileSessionManager.php
    RedisSessionManager.php
    ArraySessionManager.php    ← For tests
```

---

## Documentation

| Document | Contents |
|---|---|
| [Framework Philosophy](docs/framework-philosophy.md) | Architecture, design principles, and what the framework deliberately omits |
| [Guidelines & Usage](docs/guidelines-usage.md) | Practical reference: menus, sessions, translations, HTTP client, guards, testing |
| [Contributing & AI Prompts](docs/contributing-and-prompts.md) | Contribution guide and AI agent context |
